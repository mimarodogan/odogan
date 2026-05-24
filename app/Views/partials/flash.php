<?php
$success = flash('success');
$error = flash('error');
?>
<?php if ($success): ?>
<div class="flash flash-success" role="status"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="flash flash-error" role="alert"><?= esc($error) ?></div>
<?php endif; ?>
