    </main>
</div>

<!-- ====== SCRIPTS GLOBAIS ====== -->
<script src="assets/js/main.js"></script>
<script src="assets/js/sidebar.js"></script>
<script src="assets/js/mascaras.js"></script>

<!-- ====== SCRIPTS ESPECÍFICOS DA PÁGINA (opcional) ====== -->
<?php if (isset($scriptsExtras)): ?>
    <?php foreach ($scriptsExtras as $s): ?>
        <script src="<?= $s ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>