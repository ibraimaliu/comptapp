</div> <!-- Fermeture de la div "content" -->

    <!-- Scripts globaux -->
    <script src="assets/js/main.js"></script>
    
    <!-- Scripts spécifiques à la page -->
    <?php
    // Récupérer la page actuelle
    $current_page = isset($_GET['page']) ? $_GET['page'] : 'home';

    // Charger les scripts spécifiques en fonction de la page
    // Note: Le JavaScript pour la page 'adresses' est intégré directement dans views/adresses.php
    if ($current_page == 'comptabilite') {
        echo '<script src="assets/js/comptabilite.js"></script>';
    }
    // Vous pouvez ajouter d'autres conditions pour d'autres pages
    ?>
</body>
</html>