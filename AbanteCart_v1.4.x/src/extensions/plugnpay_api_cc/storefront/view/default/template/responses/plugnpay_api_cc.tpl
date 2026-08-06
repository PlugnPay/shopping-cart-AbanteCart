<div class="enter_card plugnpay-api-cc">
<style>
	.plugnpay-api-cc {
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
	}
	.plugnpay-api-cc .form-control,
	.plugnpay-api-cc input[type="text"],
	.plugnpay-api-cc input[type="tel"],
	.plugnpay-api-cc select {
		width: 100% !important;
		max-width: 100%;
		box-sizing: border-box;
	}
	.plugnpay-api-cc .col-form-label {
		display: block;
		width: 100%;
		white-space: normal;
		font-weight: 600;
		margin-bottom: 0.35rem;
	}
	.plugnpay-api-cc .expiry-row,
	.plugnpay-api-cc .cvv-row {
		min-width: 0;
	}
</style>

<?php echo $form_open; ?>

	<h4 class="heading4"><?php echo $text_credit_card; ?></h4>

	<?php echo $this->getHookVar('payment_table_pre'); ?>

	<div class="mb-3">
		<label for="cc_owner" class="col-form-label"><?php echo $entry_cc_owner; ?></label>
		<?php echo $cc_owner; ?>
	</div>

	<div class="mb-3">
		<label for="cc_number" class="col-form-label"><?php echo $entry_cc_number; ?></label>
		<?php echo $cc_number; ?>
	</div>

	<div class="mb-3 row g-3">
		<div class="col-12 col-md-7 expiry-row">
			<label class="col-form-label"><?php echo $entry_cc_expire_date; ?></label>
			<div class="row g-2">
				<div class="col-7"><?php echo $cc_expire_date_month; ?></div>
				<div class="col-5"><?php echo $cc_expire_date_year; ?></div>
			</div>
		</div>
		<?php if (!empty($use_cvv)) { ?>
		<div class="col-12 col-md-5 cvv-row">
			<label for="cc_cvv2" class="col-form-label">
				<?php echo $entry_cc_cvv2; ?>
				<a onclick="openModalRemote('#ccModal', '<?php echo $cc_cvv2_help_url; ?>')"
				   href="Javascript:void(0);"><?php echo $entry_cc_cvv2_short; ?></a>
			</label>
			<?php echo $cc_cvv2; ?>
		</div>
		<?php } ?>
	</div>

	<?php echo $this->getHookVar('payment_table_post'); ?>

	<div class="form-group action-buttons text-center mt-4">
		<a id="<?php echo $back->name ?>" href="<?php echo $back->href; ?>" class="btn btn-default mr10 me-2"
		   title="<?php echo $back->text ?>">
			<i class="fa fa-arrow-left"></i>
			<?php echo $back->text ?>
		</a>
		<button id="<?php echo $submit->name ?>" class="btn btn-primary" title="<?php echo $submit->text ?>"
				type="submit">
			<i class="fa fa-check"></i>
			<?php echo $submit->text; ?>
		</button>
	</div>
</form>
</div>

<!-- Modal -->
<div id="ccModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="ccModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 id="ccModalLabel" class="modal-title"><?php echo $entry_what_cvv2; ?></h5>
				<button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" aria-hidden="true">×</button>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal" aria-hidden="true"><?php echo $text_close; ?></button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
(function ($) {
	var submitSent = false;
	var sendUrl = <?php echo json_encode((string)$action); ?>;

	function clearWait($form) {
		$('.wait').remove();
		if ($form && $form.length) {
			$form.find('.action-buttons').show();
			$form.find('button[type=submit]').prop('disabled', false);
		}
		try { $('.spinner-overlay').fadeOut(200); } catch (e) {}
		submitSent = false;
	}

	function confirmSubmit($form) {
		$.ajax({
			type: 'POST',
			url: sendUrl,
			data: $form.serialize(),
			dataType: 'json',
			timeout: 60000,
			beforeSend: function () {
				$('.alert').remove();
				$form.find('.action-buttons').hide();
				$form.find('.action-buttons').before(
					'<div class="wait alert alert-info text-center"><i class="fa fa-refresh fa-spin"></i> <?php echo $text_wait; ?></div>'
				);
			},
			success: function (data) {
				if (!data) {
					clearWait($form);
					$form.before('<div class="alert alert-danger"><i class="fa fa-bug"></i> <?php echo $error_unknown; ?></div>');
					return;
				}
				if (data.error) {
					clearWait($form);
					$form.before('<div class="alert alert-warning"><i class="fa fa-exclamation"></i> ' + data.error + '</div>');
					if (data.csrfinstance) {
						$form.find('input[name=csrfinstance]').val(data.csrfinstance);
					}
					if (data.csrftoken) {
						$form.find('input[name=csrftoken]').val(data.csrftoken);
					}
					return;
				}
				if (data.success) {
					window.location = data.success;
					return;
				}
				clearWait($form);
				$form.before('<div class="alert alert-danger"><i class="fa fa-bug"></i> <?php echo $error_unknown; ?></div>');
			},
			error: function (jqXHR, textStatus, errorThrown) {
				clearWait($form);
				var detail = textStatus + (errorThrown ? (' ' + errorThrown) : '');
				if (jqXHR && jqXHR.status) {
					detail += ' (HTTP ' + jqXHR.status + ')';
				}
				$form.before('<div class="alert alert-danger"><i class="fa fa-exclamation"></i> ' + detail + '</div>');
			}
		});
	}

	// Delegated bind survives fast-checkout AJAX reloads / $('form').unbind('submit')
	$(document).off('submit.plugnpayApiCc', '#plugnpay').on('submit.plugnpayApiCc', '#plugnpay', function (event) {
		event.preventDefault();
		event.stopImmediatePropagation();

		if (submitSent === true) {
			return false;
		}

		var $form = $(this);
		var ok = (typeof validateForm === 'function')
			? validateForm($form)
			: ($form[0] && $form[0].checkValidity());

		if (!ok) {
			$form.addClass('was-validated');
			clearWait($form);
			return false;
		}

		submitSent = true;
		confirmSubmit($form);
		return false;
	});
})(jQuery);
</script>
