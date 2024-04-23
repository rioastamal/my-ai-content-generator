<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If this is a POST request, no need to display the page and redirect using javascript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<script>window.location = "' . admin_url('admin.php?page=my-ai-credentials-page&updated=true') . '";</script>';
    return;
}

?><div class="wrap">
<h1>AWS Credentials</h1>

<?php if ($success_message): ?>
<div class="updated notice notice-success is-dismissible"><p>AWS credentials saved successfully!</p></div>
<?php endif; ?>

<p>Enter your AWS credentials to use <strong>My AI Content Generator</strong>. Make sure to follow IAM best practices such as applying <a href="https://docs.aws.amazon.com/IAM/latest/UserGuide/best-practices.html#grant-least-privilege" target="_blank">principle of least-privilege</a>.</p>

<form method="post"><?php 
    settings_fields('my-ai-credentials-page'); 
?><table class="form-table">
    <tr valign="top">
        <th scope="row">Access Key ID</th>
        <td><input required type="text" size="30" name="access_key_id" value="<?php echo esc_attr($access_key_id); ?>" /></td>
    </tr>
    <tr valign="top">
        <th scope="row">Secret Access Key</th>
        <td><input required type="password" size="30" name="secret_access_key" value="" /></td>
    </tr>
</table><?php 
submit_button(); 
?></form>
</div>