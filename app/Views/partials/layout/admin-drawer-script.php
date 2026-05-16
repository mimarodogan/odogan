<?php
/**
 * Admin sidebar mobile drawer — script yükleyici.
 * Gerçek mantık assets/js/admin-drawer.js içinde. defer ile DOM hazır
 * olduğunda çalışır.
 */
?>
<script src="<?= esc(\App\Services\AssetMinifier::asset('assets/js/admin-drawer.js')) ?>" defer></script>
