<div class="login-container">
    <h2>Connexion</h2>
    
    <div id="login-message" class="alert" style="display: none;"></div>
    
    <form id="login-form">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn">Se connecter</button>
    </form>
    
    <p style="margin-top: 20px;">Pas encore de compte ? <a href="index.php?page=register">S'inscrire</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const messageDiv = document.getElementById('login-message');
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        fetch('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Important: envoyer les cookies de session
            body: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                messageDiv.className = 'alert alert-success';
                messageDiv.innerHTML = data.message;
                messageDiv.style.display = 'block';
                
                // Rediriger vers la page d'accueil
                setTimeout(function() {
                    window.location.href = 'index.php?page=home';
                }, 1000);
            } else {
                messageDiv.className = 'alert alert-danger';
                messageDiv.innerHTML = data.message;
                messageDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = 'Une erreur est survenue. Veuillez réessayer.';
            messageDiv.style.display = 'block';
        });
    });
});
</script>