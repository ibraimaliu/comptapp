# Page Paramètres - Guide Rapide

## 🚀 Démarrage Rapide

### 1. Accéder à la Page
```
http://localhost/gestion_comptable/index.php?page=parametres
```

### 2. Tester les Fonctionnalités
```
http://localhost/gestion_comptable/test_parametres.php
```

---

## 📋 9 Sections Disponibles

### 1. **Informations de la Société**
- Modifier nom, propriétaire, période comptable, statut TVA
- Bouton : "Modifier"

### 2. **QR-Factures Suisses**
- Configurer QR-IBAN et IBAN bancaire
- Adresse complète pour factures
- Validation IBAN intégrée
- Bouton : "Modifier" puis "Enregistrer"

### 3. **Plan Comptable** ⭐ (Nouvellement Opérationnel)
**Actions disponibles:**
- **Ajouter** : Créer un nouveau compte
- **Importer** : Upload fichier CSV
- **Exporter** : Télécharger en CSV
- **Réinitialiser** : Supprimer comptes non utilisés

**Format CSV pour import:**
```csv
Numéro;Intitulé;Catégorie;Type
1000;Caisse;Actif;Bilan
6000;Loyers;Charge;Résultat
```

**Fichier exemple fourni:** `plan_comptable_exemple.csv`

### 4. **Catégories de Dépenses**
- Ajouter/modifier/supprimer catégories
- Pour classifier les transactions

### 5. **Taux TVA**
- Gérer les taux de TVA
- Pré-configuré : 7.7%, 2.5%, 0%

### 6. **Exportation de Données** ⭐ (Nouveau)
**Types d'export:**
- Transactions
- Factures
- Contacts
- Plan comptable
- Toutes les données (JSON)

**Formats:**
- CSV (compatible Excel)
- JSON (sauvegarde complète)

### 7. **Profil Utilisateur** ⭐ (Nouveau)
- Modifier email
- Changer mot de passe (8+ caractères)
- Voir date de création compte

### 8. **Sécurité & Sauvegarde** ⭐ (Nouveau)
- Télécharger sauvegarde complète
- Conseils de sécurité
- Status session

### 9. **Configuration Avancée**
- Version application
- Infos système
- Société active

---

## 🔧 Utilisation Rapide

### Import Plan Comptable (Exemple Complet)

**Étape 1:** Préparer le fichier CSV
```csv
Numéro;Intitulé;Catégorie;Type
1000;Caisse;Actif;Bilan
1020;Banque;Actif;Bilan
6000;Loyers;Charge;Résultat
```

**Étape 2:** Dans l'application
1. Paramètres → Plan comptable
2. Cliquer "Importer"
3. Sélectionner votre fichier CSV
4. Choisir :
   - "Remplacer" : Efface les comptes non utilisés et importe
   - "Ajouter" : Conserve tout et ajoute les nouveaux
5. Cliquer "Importer"

**Résultat:**
```
✅ 32 comptes importés avec succès

Avertissements:
- Ligne ignorée pour compte 1000: existe déjà
```

### Export Données (Exemple)

**Scénario:** Exporter toutes les factures en CSV pour Excel

1. Paramètres → Exportation de données
2. Type : "Factures"
3. Format : "CSV (Excel compatible)"
4. Cliquer "Télécharger l'export"
5. Le fichier `factures_2024-11-12_15-30-45.csv` est téléchargé
6. Ouvrir dans Excel → Tout est bien formaté ✅

### Changement Mot de Passe (Exemple)

1. Paramètres → Profil utilisateur
2. Entrer mot de passe actuel : `MonAncienPass123`
3. Nouveau mot de passe : `MonNouveauPass456!`
4. Confirmer : `MonNouveauPass456!`
5. Cliquer "Changer le mot de passe"
6. ✅ "Mot de passe modifié avec succès"
7. Les champs sont vidés automatiquement

---

## 🐛 Résolution de Problèmes

### Problème : Import CSV échoue

**Erreur:** "Format CSV invalide"

**Solutions:**
1. Vérifier séparateur : doit être `;` (point-virgule)
2. Vérifier colonnes : Numéro;Intitulé;Catégorie;Type
3. Vérifier catégories : Actif, Passif, Charge, ou Produit
4. Vérifier types : Bilan ou Résultat

**Exemple correct:**
```csv
Numéro;Intitulé;Catégorie;Type
1000;Caisse;Actif;Bilan
```

**Exemple incorrect:**
```csv
Number,Name,Category,Type
1000,Caisse,Asset,Balance
```

### Problème : Export vide

**Solutions:**
1. Vérifier qu'une société est sélectionnée
2. Vérifier que vous avez des données dans cette catégorie
3. Se déconnecter et reconnecter

### Problème : Changement mot de passe refusé

**Solutions:**
1. Vérifier mot de passe actuel correct
2. Nouveau mot de passe ≥ 8 caractères
3. Nouveaux mots de passe identiques
4. Pas d'espaces avant/après

---

## 📚 Documentation Complète

### Fichiers de Documentation

1. **GUIDE_PARAMETRES.md** (450 lignes)
   - Guide utilisateur détaillé
   - Instructions pas à pas
   - Troubleshooting complet

2. **REFONTE_PARAMETRES_COMPLETE.md** (600 lignes)
   - Documentation technique
   - Architecture
   - Tests recommandés

3. **plan_comptable_exemple.csv**
   - 32 comptes PME suisse
   - Prêt à importer

4. **test_parametres.php**
   - Page de tests interactifs
   - Vérification rapide

---

## ✅ Checklist de Vérification

### Après Installation

- [ ] Page paramètres accessible
- [ ] Import CSV fonctionne avec `plan_comptable_exemple.csv`
- [ ] Export CSV télécharge un fichier
- [ ] Profil utilisateur charge les données
- [ ] Changement mot de passe fonctionne
- [ ] Toutes les sections visibles
- [ ] Pas d'erreurs JavaScript dans console (F12)

### Tests Rapides

```bash
# Test 1: Fichiers présents
ls assets/ajax/accounting_plan_import.php  # ✅ Existe
ls assets/ajax/user_profile.php            # ✅ Existe
ls assets/ajax/data_export.php             # ✅ Existe

# Test 2: Accès page
curl http://localhost/gestion_comptable/test_parametres.php

# Test 3: Import exemple
# Via interface web: Paramètres → Plan comptable → Importer → plan_comptable_exemple.csv
```

---

## 🎯 Fonctionnalités Clés

### ✨ Points Forts

1. **Import CSV Robuste**
   - Validation complète
   - Normalisation automatique
   - Rapport détaillé
   - Gestion erreurs

2. **Export Multi-Format**
   - CSV compatible Excel
   - JSON pour backups
   - Encodage UTF-8 correct
   - Nommage horodaté

3. **Sécurité Renforcée**
   - Hash bcrypt
   - Validation 8+ caractères
   - Vérification mot de passe actuel
   - Protection CSRF (existante)

4. **UX Optimale**
   - Messages clairs
   - Loading indicators
   - Notifications
   - Responsive mobile

---

## 🔗 Liens Utiles

- **Page Paramètres**: `index.php?page=parametres`
- **Page de Tests**: `test_parametres.php`
- **Guide Complet**: `GUIDE_PARAMETRES.md`
- **Doc Technique**: `REFONTE_PARAMETRES_COMPLETE.md`

---

## 💡 Astuces

### 1. Sauvegarde Régulière
```
Paramètres → Sécurité & Sauvegarde
→ "Télécharger sauvegarde complète (JSON)"
→ Conserver mensuellement
```

### 2. Import Rapide Plan Comptable
```
1. Télécharger plan_comptable_exemple.csv
2. Modifier selon vos besoins dans Excel
3. Sauvegarder en CSV (séparateur point-virgule)
4. Importer via interface
```

### 3. Export pour Comptable
```
1. Export "Toutes les données" → JSON
2. Ou exports séparés en CSV pour Excel
3. Partager avec votre comptable
```

---

## ⚡ Raccourcis

| Action | Chemin |
|--------|--------|
| Importer plan | Paramètres → Plan comptable → Importer |
| Exporter plan | Paramètres → Plan comptable → Exporter |
| Changer MDP | Paramètres → Profil utilisateur → Changer le mot de passe |
| Export données | Paramètres → Exportation de données → Choisir type |
| Sauvegarde | Paramètres → Sécurité & Sauvegarde → Télécharger |

---

## 📞 Support

En cas de problème :

1. ✅ Consulter `GUIDE_PARAMETRES.md`
2. ✅ Vérifier console navigateur (F12)
3. ✅ Tester avec `test_parametres.php`
4. ✅ Vérifier permissions fichiers
5. ✅ Contacter administrateur

---

**Version**: 2.0.0
**Dernière mise à jour**: 12 novembre 2024
**Status**: ✅ Production Ready
