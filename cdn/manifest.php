CACHE MANIFEST
# <?php echo date(); ?>

# Explicitly cached 'master entries'.
CACHE:
<?php foreach ($cachedAssets as $path): ?>
	<?php ehco $path; ?>
<?php endforeach; ?>