    </main>
    <?php if (Auth::check()): ?>
        <?php require __DIR__ . '/modal_whatsapp.php'; ?>
    <?php endif; ?>
    <script src="assets/js/app.js"></script>
</body>
</html>
