<div class="field">
    <label for="openlayerszoom_tiles_dir">
        <?php echo __('Directory path of tiles files'); ?>
    </label>
    <?php echo get_view()->formText('openlayerszoom_tiles_dir', get_option('openlayerszoom_tiles_dir'), array('size' => 50)); ?>
    <p class="explanation">
        <?php echo __('Directory path where tiles files are stored.');
        echo ' ' . __('Default directory is "%s".', get_option('openlayerszoom_tiles_dir')); ?>
    </p>
</div>
<div class="field">
    <label for="openlayerszoom_tiles_web">
        <?php echo __('Base Url of tiles files'); ?>
    </label>
    <?php echo get_view()->formText('openlayerszoom_tiles_web', get_option('openlayerszoom_tiles_web'), array('size' => 50)); ?>
    <p class="explanation">
        <?php echo __('Equivalent web url.'); ?>
    </p>
</div>
