# ✅ Checklist Tests Export PDF

## 🎯 Objectif
Valider le système complet d'export PDF pour devis et factures avec QR-factures suisses.

---

## 🚀 Préparation

### 1. Accès à l'application
- [ ] Connexion à http://localhost/gestion_comptable
- [ ] Session active avec société sélectionnée
- [ ] Au moins 1 devis existant
- [ ] Au moins 1 facture existante

### 2. Script de test automatique
- [ ] Accéder à: http://localhost/gestion_comptable/test_pdf_generation.php
- [ ] Vérifier tous les checks verts ✅
- [ ] Noter les warnings éventuels

---

## 📋 Tests Devis (Quote PDF)

### Test 1: Export PDF Basique

**Étapes:**
1. Aller sur **Devis** (menu latéral)
2. Localiser un devis existant
3. Cliquer sur le bouton **PDF**
4. Attendre le téléchargement

**Vérifications:**
- [ ] Téléchargement automatique déclenché
- [ ] Nom fichier: `devis_DEV-2024-XXX.pdf`
- [ ] Fichier s'ouvre sans erreur
- [ ] Taille fichier: ~50-200 KB

### Test 2: Contenu PDF Devis

**Ouvrir le PDF et vérifier:**

**En-tête:**
- [ ] Nom société présent
- [ ] Adresse société complète
- [ ] Téléphone (si configuré)
- [ ] Email (si configuré)

**Titre:**
- [ ] "DEVIS / OFFRE" en violet
- [ ] Taille 20pt, bien visible

**Méta-données:**
- [ ] Numéro: DEV-2024-XXX
- [ ] Date: format d.m.Y (ex: 11.11.2024)
- [ ] Valable jusqu'au: format d.m.Y

**Adresse client:**
- [ ] Dans un cadre
- [ ] Nom client correct
- [ ] Adresse complète
- [ ] NPA et ville

**Tableau items:**
- [ ] Colonnes: Description, Quantité, Prix HT, TVA%, Total
- [ ] Toutes les lignes présentes
- [ ] Quantités: format décimal (ex: 2.00)
- [ ] Prix: format suisse avec espaces (ex: 1 234.56 CHF)
- [ ] TVA: pourcentage correct (ex: 7.7%)

**Totaux:**
- [ ] Sous-total HT
- [ ] Montant TVA
- [ ] Total TTC en gras
- [ ] Tous les montants alignés à droite

**Informations validité:**
- [ ] Cadre bleu clair
- [ ] Date limite validité affichée
- [ ] Mention TVA incluse

**Notes (si présentes):**
- [ ] Cadre gris avec bordure bleue
- [ ] Texte formaté (sauts de ligne respectés)

**Pied de page:**
- [ ] Message de remerciement
- [ ] Ligne de séparation

### Test 3: Formatage et Qualité

- [ ] **Accents**: é, è, à, ç correctement affichés
- [ ] **Police**: Arial/Helvetica lisible
- [ ] **Marges**: Équilibrées (15mm gauche/droite, 20mm haut/bas)
- [ ] **Alignements**: Texte justifié correctement
- [ ] **Couleurs**: Violet (#667eea) bien visible
- [ ] **Impression**: Prévisualisation OK

### Test 4: Cas Limites Devis

**Devis avec beaucoup d'items (10+):**
- [ ] Toutes les lignes présentes
- [ ] Pagination automatique si nécessaire
- [ ] Totaux corrects

**Devis avec notes longues:**
- [ ] Texte complet affiché
- [ ] Pas de débordement
- [ ] Sauts de ligne respectés

**Devis sans téléphone/email société:**
- [ ] Pas de lignes vides
- [ ] Formatage correct

**Client sans adresse complète:**
- [ ] Nom affiché
- [ ] Pas d'erreur
- [ ] Champs manquants: "N/A" ou vides élégamment

---

## 🧾 Tests Factures (Invoice PDF avec QR-Code)

### Test 5: Export PDF Facture Basique

**Étapes:**
1. Aller sur **Factures** (menu latéral)
2. Localiser une facture existante
3. Cliquer sur le bouton **PDF**
4. Attendre le téléchargement

**Vérifications:**
- [ ] Téléchargement automatique
- [ ] Nom fichier: `facture_FACT-2024-XXX.pdf`
- [ ] Fichier s'ouvre sans erreur
- [ ] Taille: ~100-300 KB (plus lourd que devis à cause du QR)

### Test 6: Contenu PDF Facture (Sections 1-6)

**Sections identiques au devis:**
- [ ] En-tête société
- [ ] Titre "FACTURE"
- [ ] Méta-données (numéro, date, échéance)
- [ ] Adresse client
- [ ] Tableau items
- [ ] Totaux

### Test 7: Section QR-Facture

**Ligne de découpe:**
- [ ] Ligne pointillée visible
- [ ] Texte "Section de paiement" ou équivalent

**QR-Code:**
- [ ] QR-Code présent (200x200px)
- [ ] Bien positionné (à gauche)
- [ ] Net et scannable

**Informations QR (à droite du QR):**

**Compte / Payable à:**
- [ ] QR-IBAN formaté: CH44 3199 9123... (espaces tous les 4)
- [ ] Nom société
- [ ] Adresse société complète

**Référence:**
- [ ] QR-Reference: 27 chiffres
- [ ] Format: XX XXXXX XXXXX XXXXX XXXXX XXXXX
- [ ] Exemple: 00 00010 00000 00000 00000 00056

**Montant:**
- [ ] Total TTC en CHF
- [ ] Format suisse (ex: 1 234.56 CHF)

**Payable par:**
- [ ] Nom client
- [ ] Adresse client complète

### Test 8: Scan QR-Code

**Avec application bancaire suisse:**

**Applications à tester:**
- [ ] UBS TWINT
- [ ] PostFinance App
- [ ] Raiffeisen E-Banking
- [ ] Credit Suisse App
- [ ] Ou tout scanner QR universel

**Vérifications après scan:**
- [ ] IBAN correctement lu
- [ ] Montant: correspond au total facture
- [ ] Référence: 27 chiffres présents
- [ ] Bénéficiaire: nom société correct
- [ ] Débiteur: nom client correct (si supporté)

**Si scan échoue:**
- Vérifier QR-IBAN configuré dans paramètres société
- Vérifier format QR-Code (Swiss QR ISO 20022)
- Consulter logs: `c:\xampp\apache\logs\error.log`

### Test 9: QR-Reference Validation

**Vérifier structure:**
```
00001 000000000000000000005 6
│     │                      │
│     │                      └─ Checksum (1 chiffre)
│     └─ Invoice number (21 chiffres)
└─ Company ID (5 chiffres)
```

**Tests:**
- [ ] Longueur: exactement 27 chiffres
- [ ] Format: uniquement des chiffres (0-9)
- [ ] Company ID: correspond à votre société
- [ ] Invoice number: correspond au numéro facture
- [ ] Checksum: valide (calculateur externe disponible sur paymentstandards.ch)

### Test 10: Cas Limites Factures

**Facture sans QR-IBAN configuré:**
- [ ] PDF généré quand même
- [ ] Section QR avec IBAN standard (si disponible)
- [ ] Ou message "QR-IBAN non configuré"

**Facture avec montant élevé:**
- [ ] Montant > 10'000 CHF scannable
- [ ] Formatage correct (espaces milliers)

**Facture multiple devise (si implémenté):**
- [ ] Devise affichée (EUR, USD)
- [ ] QR-Code adapté

---

## 🔬 Tests Performance

### Test 11: Génération Rapide

**Mesurer temps de génération:**

**Devis:**
- [ ] < 1 seconde (acceptable)
- [ ] ~200-500ms (optimal)

**Facture:**
- [ ] < 1.5 secondes (acceptable)
- [ ] ~400-800ms (optimal)

**Avec 10 items:**
- [ ] < 2 secondes

**Si trop lent (> 3 secondes):**
- Vérifier mémoire serveur
- Vérifier logs d'erreurs
- Optimiser images QR-Code

### Test 12: Génération Multiple

**Générer 5 PDFs rapidement:**
1. Cliquer "PDF" sur 5 factures différentes
2. Vérifier tous les téléchargements

**Vérifications:**
- [ ] Tous les PDFs générés
- [ ] Noms fichiers uniques (timestamp)
- [ ] Pas de collision
- [ ] Contenu correct pour chaque facture

---

## 🛡️ Tests Sécurité

### Test 13: Contrôle Accès

**Tester accès non autorisé:**

**Sans session:**
```
http://localhost/gestion_comptable/assets/ajax/export_invoice_pdf.php?id=1
```
- [ ] HTTP 401 Unauthorized
- [ ] Message: "Non autorisé"

**ID invalide:**
```
?id=abc
?id=-1
?id=0
```
- [ ] HTTP 400 Bad Request
- [ ] Message: "ID invalide"

**ID d'une autre société:**
```
?id=999 (facture d'une autre société)
```
- [ ] HTTP 404 Not Found
- [ ] Message: "Facture introuvable"

### Test 14: Injection SQL

**Tenter injection:**
```
?id=1' OR '1'='1
?id=1; DROP TABLE invoices;
```
- [ ] Pas d'erreur SQL
- [ ] Requête bloquée ou ID=0

---

## 🌐 Tests Compatibilité

### Test 15: Navigateurs

**Tester téléchargement PDF sur:**
- [ ] Chrome/Edge (Windows)
- [ ] Firefox (Windows)
- [ ] Safari (macOS)
- [ ] Chrome (Android)
- [ ] Safari (iOS)

### Test 16: Lecteurs PDF

**Ouvrir PDFs générés avec:**
- [ ] Adobe Acrobat Reader
- [ ] Foxit Reader
- [ ] Edge (lecteur intégré)
- [ ] Chrome (lecteur intégré)
- [ ] macOS Preview

**Vérifier:**
- [ ] Police correcte
- [ ] Couleurs fidèles
- [ ] QR-Code scannable
- [ ] Impression correcte

---

## 🐛 Tests Erreurs

### Test 17: Données Manquantes

**Créer facture avec champs vides:**
- [ ] Client sans adresse → PDF généré
- [ ] Items sans description → Erreur ou ligne vide
- [ ] Montant 0 → PDF généré

### Test 18: Dossier uploads Inexistant

**Simuler erreur:**
1. Renommer `uploads/invoices` en `uploads/invoices_backup`
2. Générer une facture PDF

**Vérifications:**
- [ ] Dossier automatiquement recréé
- [ ] OU message d'erreur clair
- [ ] PDF généré après recréation

### Test 19: Permissions Fichiers

**Simuler manque de permissions:**
1. Rendre `uploads/invoices` non-writable (chmod 444)
2. Tenter génération PDF

**Vérifications:**
- [ ] Message d'erreur clair
- [ ] Logged dans Apache error.log
- [ ] Pas de crash serveur

---

## 📊 Résultats Tests

### Statistiques Globales

**Tests Devis (1-4):**
- Total: __ / __ tests passés
- Bugs critiques: __
- Bugs mineurs: __

**Tests Factures (5-10):**
- Total: __ / __ tests passés
- Bugs critiques: __
- Bugs mineurs: __

**Tests Performance (11-12):**
- Total: __ / __ tests passés
- Optimisations nécessaires: __

**Tests Sécurité (13-14):**
- Total: __ / __ tests passés
- Vulnérabilités: __

**Tests Compatibilité (15-16):**
- Total: __ / __ tests passés
- Incompatibilités: __

**Tests Erreurs (17-19):**
- Total: __ / __ tests passés
- Gestion erreurs: __

### Score Global

**Total général: __ / 100+ tests passés**

**Statut:**
- [ ] ✅ Production Ready (> 95%)
- [ ] 🟡 Corrections mineures nécessaires (85-95%)
- [ ] 🔴 Corrections majeures nécessaires (< 85%)

---

## 🔧 Actions Correctives

### Bugs Identifiés

| # | Priorité | Bug | Status | Solution |
|---|----------|-----|--------|----------|
| 1 | 🔴 Haute | QR-Code non scannable | ❌ | Vérifier format ISO 20022 |
| 2 | 🟡 Moyenne | Accents mal affichés | ❌ | Vérifier encoding UTF-8 |
| 3 | 🟢 Basse | Marges trop larges | ❌ | Ajuster paramètres mPDF |

### Améliorations Suggérées

1. **Prévisualisation inline** - Afficher PDF dans modal avant téléchargement
2. **Personnalisation** - Permettre upload logo société
3. **Multi-langues** - Support FR/DE/IT/EN
4. **Email automatique** - Envoi direct depuis interface
5. **Archivage** - Conservation versions PDFs

---

## 📚 Ressources

### Documentation
- [GUIDE_PDF_EXPORT.md](GUIDE_PDF_EXPORT.md) - Guide complet
- [SESSION_SUMMARY_2024_11_11_PART3.md](SESSION_SUMMARY_2024_11_11_PART3.md) - Résumé implémentation

### Outils Externes
- **Validateur QR-Reference**: https://www.paymentstandards.ch/
- **Spec Swiss QR**: https://www.swiss-qr-invoice.org/
- **mPDF Docs**: https://mpdf.github.io/

### Scripts Utiles
- **test_pdf_generation.php** - Tests automatiques
- **uploads/** - Dossiers PDFs générés

---

## ✅ Validation Finale

**Signature testeur:**

- Nom: __________________
- Date: __________________
- Environnement: Windows / macOS / Linux
- Version PHP: __________
- Version mPDF: __________

**Commentaires:**
```
[Vos observations ici]
```

**Conclusion:**
- [ ] ✅ Système validé pour production
- [ ] 🟡 Validé avec réserves (préciser)
- [ ] 🔴 Non validé (corrections nécessaires)

---

**Version**: 1.0
**Date**: 11 Novembre 2024
**Auteur**: Gestion Comptable Team
