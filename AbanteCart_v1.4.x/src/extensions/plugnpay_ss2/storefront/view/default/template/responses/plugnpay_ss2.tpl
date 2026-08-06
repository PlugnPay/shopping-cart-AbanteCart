<?php echo $form['form_open']; ?>
<?php if (!empty($error)) { ?>
<div class="alert alert-danger"><i class="fa fa-exclamation"></i> <?php echo $error; ?></div>
<?php } else { ?>
<div class="wait alert alert-info text-center">
	<i class="fa fa-refresh fa-spin"></i> <?php echo $text_redirecting; ?>
</div>
<?php
foreach ($form['fields'] as $field) {
	echo $field;
}
?>
<div class="form-group action-buttons text-center" id="plugnpay-ss2-noscript">
	<button class="btn btn-orange lock-on-click" title="<?php echo $form['submit']->name; ?>" type="submit">
		<i class="fa fa-check"></i>
		<?php echo $form['submit']->name; ?>
	</button>
</div>
<?php } ?>
</form>

<?php if (!empty($auto_submit) && empty($error)) { ?>
<script type="text/javascript">
	jQuery(document).ready(function () {
		var $form = jQuery('#plugnpay-ss2-hosted-form');
		if ($form.length) {
			jQuery('#plugnpay-ss2-noscript').hide();
			$form.submit();
		}
	});
</script>
<?php } ?>
