<?php
$useSidebar = $useSidebar ?? false;
$isPublic   = $isPublic   ?? false;
?>

<?php if ($useSidebar): ?>
</div><!-- /.app-layout -->
<?php endif; ?>

<?php if ($isPublic): ?>
<!-- Public site footer is in home.php itself -->
<?php else: ?>
<footer class="site-footer">
    &copy; <?php echo date('Y'); ?> MediCare Hospital Management System &mdash;
    ICT1242 Web Development Practicum &mdash; Group 05
</footer>
<?php endif; ?>

</body>
</html>
