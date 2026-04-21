<?php
// Inclure les configurations
include_once 'config/config.php';

// Rediriger vers la page d'accueil si l'utilisateur est déjà connecté
if(isLoggedIn()) {
    redirect('index.php?page=home');
}
?>

<div class="register-container">
    <h2>Inscription</h2>
    
    <div id="register-message" class="alert" style="display: none;"></div>
    
    <form id="register-form">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" required>
            <small class="form-text text-muted">Choisissez un nom d'utilisateur unique.</small>
        </div>
        
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" required>
            <small class="form-text text-muted">Nous ne partagerons jamais votre email.</small>
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
            <small class="form-text text-muted">Votre mot de passe doit contenir au moins 8 caractères.</small>
        </div>
        
        <div class="form-group">
            <label for="confirm-password">Confirmer le mot de passe</label>
            <input type="password" id="confirm-password" name="confirm-password" required>
        </div>
        
        <button type="submit" class="btn">S'inscrire</button>
    </form>
    
    <p style="margin-top: 20px;">Vous avez déjà un compte ? <a href="index.php?page=login">Se connecter</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form');
    const messageDiv = document.getElementById('register-message');
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        // Validation côté client
        if (password.length < 8) {
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = 'Le mot de passe doit contenir au moins 8 caractères.';
            messageDiv.style.display = 'block';
            return;
        }
        
        if (password !== confirmPassword) {
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = 'Les mots de passe ne correspondent pas.';
            messageDiv.style.display = 'block';
            return;
        }
        
        // Envoi de la requête d'inscription
        fetch('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'register',
                username: username,
                email: email,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                messageDiv.className = 'alert alert-success';
                messageDiv.innerHTML = data.message;
                messageDiv.style.display = 'block';
                
                // Rediriger vers la page de connexion après 2 secondes
                setTimeout(function() {
                    window.location.href = 'index.php?page=login';
                }, 2000);
            } else {
                messageDiv.className = 'alert alert-danger';
                messageDiv.innerHTML = data.message;
                messageDiv.style.display = 'block';
            }
        })
        // Remplacez le bloc .catch dans le script de register.php
.catch(error => {
    console.error('Erreur:', error);
    messageDiv.className = 'alert alert-danger';
    messageDiv.innerHTML = 'Une erreur est survenue lors de la communication avec le serveur. Vérifiez les logs.';
    messageDiv.style.display = 'block';
    
    // Afficher plus de détails de l'erreur dans la console
    if (error.response) {
        console.log('Réponse d\'erreur du serveur:', error.response);
    }
});

// Et modifie.z le bloc .then pour afficher les détails complets de la réponse
then(data => {
    console.log('Réponse complète:', data); // Afficher la réponse complète dans la console
    if(data.success) {
        // ...reste du code inchangé...
    } else {
        messageDiv.className = 'alert alert-danger';
        messageDiv.innerHTML = data.message || 'Erreur inconnue.';
        if (data.debug) {
            console.error('Détails de l\'erreur:', data.debug);
        }
        messageDiv.style.display = 'block';
    }
})
    });
});
</script>