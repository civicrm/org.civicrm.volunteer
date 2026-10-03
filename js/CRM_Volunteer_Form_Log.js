CRM.$(function($) {
  var requiredFields = '.crm-vol-contact, .crm-vol-actual-duration';
  var $table = $('#crm-log-entry-table');
  var $form = $table.closest('form');

  function updateVisibleRows() {
    var visibleCount = $table.find('.crm-vol-log-entry.crm-grid-row').length;
    $('.crm-vol-log-visible-count').text(visibleCount);
    $('#addMoreVolunteer').prop('disabled', !$table.find('.hiddenElement').length);
  }

  function markDirty() {
    $('.crm-vol-log-unsaved').addClass('is-visible');
  }

  function labelRowControls($row) {
    var rowNumber = $row.data('row-number');
    $row.find('.crm-grid-cell[data-label]').each(function() {
      var label = $(this).data('label');
      $(this).find(':input:not([type=hidden]):not(button)').attr('aria-label', label + ' ' + rowNumber);
    });
  }

  function activateRow($row) {
    $row.show()
      .removeClass('hiddenElement')
      .addClass('crm-grid-row')
      .find(requiredFields)
      .addClass('required');
    labelRowControls($row);
    markDirty();
    updateVisibleRows();
  }

  // Only active rows are required. Hidden rows are prebuilt for fast batch entry.
  $table.find('.crm-grid-row').each(function() {
    var $row = $(this);
    $row.find(requiredFields).addClass('required');
    labelRowControls($row);
  });

  $('#addMoreVolunteer').click(function(e) {
    e.preventDefault();
    var $row = $table.find('.hiddenElement:first');
    if ($row.length) {
      activateRow($row);
      $row.find('.crm-vol-contact').first().focus();
    }
  });

  $table.on('click', '.crm-vol-remove-row', function(e) {
    e.preventDefault();
    var $row = $(this).closest('.crm-grid-row');
    $row.slideUp(100, function() {
      $row.remove();
      markDirty();
      updateVisibleRows();
    });
  });

  $table.on('change input', ':input', markDirty);
  $('.crm-vol-batch-action').on('click', function() {
    markDirty();
  });
  $form.on('submit', function() {
    $('.crm-vol-log-unsaved').removeClass('is-visible');
  });

  updateVisibleRows();
});
