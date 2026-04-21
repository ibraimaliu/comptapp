# Fix: Company Selection Reverting Bug

## Problem Description

When users selected a different company from either:
- The company selector dropdown in the header
- The "Mes Sociétés" page

The selection would appear to change momentarily but then revert back to the previously selected company after page reload.

## Root Cause

**Critical Bug in `api/session.php`:**

The file was starting the session **without the custom session name** that the rest of the application uses:

```php
// INCORRECT - Using PHP default session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
```

The entire application uses `session_name('COMPTAPP_SESSION')` to set a custom session name, but `api/session.php` was missing this call.

**Impact:**
- When the API updated `$_SESSION['company_id']`, it was updating the **wrong session** (PHP's default session)
- When the page reloaded, it read from the **correct session** (`COMPTAPP_SESSION`), which still had the old `company_id`
- Result: Selection appeared to revert

**Secondary Bug in `includes/header.php`:**

The fiscal year selector was always pre-selecting the current year instead of the session value:

```php
// INCORRECT - Always selects current year
<?php echo ($fy['year'] == date('Y')) ? 'selected' : ''; ?>
```

## Fixes Applied

### 1. Fixed Session Name in `api/session.php`

**File:** `api/session.php` (Lines 2-4)

```php
<?php
// IMPORTANT: Utiliser le même nom de session que le reste de l'application
session_name('COMPTAPP_SESSION');

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
```

### 2. Enhanced Debugging in `api/session.php`

**File:** `api/session.php` (Lines 61-74)

Added comprehensive logging to track session changes:

```php
// Mettre à jour la session
$old_company_id = $_SESSION['company_id'] ?? 'aucune';
$_SESSION['company_id'] = $data['company_id'];
error_log('CHANGEMENT DE SOCIÉTÉ - Ancienne: ' . $old_company_id . ' → Nouvelle: ' . $_SESSION['company_id']);
error_log('Session ID: ' . session_id());
error_log('Session complète après changement: ' . print_r($_SESSION, true));

// Répondre avec succès
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'company_id' => $_SESSION['company_id'],
    'old_company_id' => $old_company_id
]);
```

### 3. Fixed Fiscal Year Selector in `includes/header.php`

**File:** `includes/header.php` (Lines 171-180)

```php
<select id="fiscal-year-selector" class="selector-dropdown" onchange="switchFiscalYear(this.value)">
    <?php
    $selected_year = $_SESSION['fiscal_year'] ?? date('Y');
    foreach ($fiscal_years as $fy):
    ?>
        <option value="<?php echo $fy['year']; ?>"
                <?php echo ($fy['year'] == $selected_year) ? 'selected' : ''; ?>>
            <?php echo $fy['label']; ?>
        </option>
    <?php endforeach; ?>
</select>
```

### 4. Added Console Logging for Frontend Debugging

**Files:** `includes/header.php` and `views/mes_societes.php`

```javascript
function switchCompany(companyId) {
    if (!companyId) return;

    console.log('Changement de société vers ID:', companyId);

    fetch('api/session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'change_company',
            company_id: parseInt(companyId)
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Réponse de l\'API:', data);
        if (data.success) {
            console.log('Changement réussi! Ancien:', data.old_company_id, '→ Nouveau:', data.company_id);
            window.location.reload();
        } else {
            alert('Erreur lors du changement de société: ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de communication avec le serveur');
    });
}
```

## How to Test

1. **Login** to your account
2. **Open browser console** (F12) to see debug messages
3. **Select a different company** from the dropdown in the header or from "Mes Sociétés"
4. **Check console logs** to verify:
   - "Changement de société vers ID: X"
   - "Réponse de l'API: {success: true, company_id: X, old_company_id: Y}"
   - "Changement réussi! Ancien: Y → Nouveau: X"
5. **Page reloads** and the new company should remain selected
6. **Check Apache error log** to see server-side logs:
   - "CHANGEMENT DE SOCIÉTÉ - Ancienne: Y → Nouvelle: X"
   - Full session contents

## Expected Behavior After Fix

- ✅ Company selection persists after page reload
- ✅ Fiscal year selection persists after page reload
- ✅ Switching companies in header updates session correctly
- ✅ Switching companies in "Mes Sociétés" page works correctly
- ✅ Both client-side and server-side logging available for debugging

## Files Modified

1. `api/session.php` - Added session_name(), enhanced logging, improved response
2. `includes/header.php` - Fixed fiscal year selector, added console logging
3. `views/mes_societes.php` - Added console logging to switchCompany function

## Lesson Learned

**Always use the same session name across the entire application!**

When using `session_name()` to set a custom session name, **every single PHP file** that uses sessions must call `session_name()` with the same name **before** calling `session_start()`.

This includes:
- API endpoints
- AJAX handlers
- Include files
- Any file that accesses `$_SESSION`

Otherwise, different parts of the application will be reading/writing to different sessions!
