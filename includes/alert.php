<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="animate-fadeIn mb-5 p-4 rounded-lg flex items-center gap-3 text-sm
    <?php if ($flash['type'] === 'success'): ?>bg-green-50 text-green-700 border border-green-200
    <?php elseif ($flash['type'] === 'danger'): ?>bg-red-50 text-red-700 border border-red-200
    <?php elseif ($flash['type'] === 'warning'): ?>bg-yellow-50 text-yellow-700 border border-yellow-200
    <?php else: ?>bg-blue-50 text-blue-700 border border-blue-200<?php endif; ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>
