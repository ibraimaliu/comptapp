-- Mise à jour de subscription_plans pour ajouter max_employees

-- Ajouter la colonne si elle n'existe pas
ALTER TABLE subscription_plans
ADD COLUMN max_employees INT DEFAULT 0 COMMENT 'Nombre max employés (-1 = illimité)';

-- Mettre à jour les limites par plan
UPDATE subscription_plans SET max_employees = 0 WHERE plan_code = 'free';
UPDATE subscription_plans SET max_employees = 3 WHERE plan_code = 'starter';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'professional';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'enterprise';

-- Afficher le résultat
SELECT plan_code, plan_name, max_employees FROM subscription_plans;
