</main>

<footer class="site-footer">
    <p>⚛ NexusSync - Goal Setting & Tracking Portal &copy; <?= date('Y') ?></p>
</footer>

<script src="/assets/js/app.js"></script>
<?php if (!empty($use_chartjs)): ?>
<script src="/assets/vendor/chart.min.js"></script>
<?php endif; ?>
<?php if (!empty($page_scripts)): ?>
<script><?= $page_scripts ?></script>
<?php endif; ?>
</body>
</html>
