<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If this is a POST request, no need to display the page and redirect using javascript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<script>window.location = "' . admin_url('admin.php?page=my-ai-models-page&updated=true') . '";</script>';
    return;
}

?><div class="wrap">

<h1>My AI Content Generator</h1>
<?php if ($success_message) : ?>
<div class="updated notice notice-success is-dismissible"><p>Selected models saved successfully!</p></div>
<?php endif; ?>

<p>My AI Content Generator helps you write content quickly and efficiently using AI.</p>

<h2>Select Foundation Models</h2>
<p>Please select the foundation models you want to use to generate your content. Each foundation model is trained on a specific dataset and can be used to generate content of different types and sizes. Currently the default region is set to <strong>us-east-1</strong>.</p>

<?php if (isset($foundation_models['error'])) : ?>
    <p>No foundation models found. </p>
    <p>Make sure your href="<?php echo esc_url($aws_credentials_link); ?>">AWS credentials</a> is correct and having proper permissions.</p>
    <p><strong>Message</strong>:<br><i><?php echo esc_html($foundation_models['error']); ?></i></p>
<?php return; endif; ?>
    <form method="post"><?php 
    settings_fields('my-ai-models-page');
    $counter = 0;
    
    ?><table class="widefat striped">
        <thead>
            <tr>
            <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox">
                <label for="cb-select-all-1"><span class="screen-reader-text">Select All</span></label></td>
                <th>No</th>
                <th>Name</th>
                <th>Id</th>
                <th>Provider</th>
                <th>Input</th>
                <th>Output</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($foundation_models as $foundation_model) : ?>
                <?php
                // Only show model which outputModalities is TEXT
                $is_outputmodality_text = in_array('TEXT', $foundation_model['outputModalities']);
                if (! $is_outputmodality_text) {
                    continue;
                }

                // Only show supported model, which the model id is not end with suffix any number + 'k'
                $is_supported_model = ! preg_match('/\d+k$/', $foundation_model['modelId']);
                if (! $is_supported_model) {
                    continue;
                }

                // Exclude model ids which not support on-demand throughput
                $excluded_model_ids = ['meta.llama2-13b-v1', 'meta.llama2-70b-v1'];
                if (in_array($foundation_model['modelId'], $excluded_model_ids)) {
                    continue;
                }

                // Define checked variable when current foundation_model is selected
                $checked = in_array($foundation_model['modelId'], $selected_foundation_models) ? 'checked' : '';
                ?><tr class="iedit">
                    <td class="check-column" style="padding: 8px 10px"><input <?php echo $checked; ?> type="checkbox" name="foundation_models[]" value="<?php echo esc_attr($foundation_model['modelId']); ?>"></td>
                    <td><?php echo ++$counter; ?></td>
                    <td><?php echo esc_html($foundation_model['modelName']); ?></td>
                    <td><?php echo esc_html($foundation_model['modelId']); ?></td>
                    <td><?php echo esc_html($foundation_model['providerName']); ?></td>
                    <td><?php echo esc_html(implode(', ', $foundation_model['inputModalities'])); ?></td>
                    <td><?php echo esc_html(implode(', ', $foundation_model['outputModalities'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table><?php 
    
    submit_button(); ?>
</form>

</div>