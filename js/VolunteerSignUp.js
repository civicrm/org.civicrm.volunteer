CRM.$(function($) {

  function addProfileRow() {
    var newRowIndex = $("#additionalVolunteers .additional-volunteer-profile").length;
    var container = $(".crm-volunteer-additional-volunteers-template .additional-volunteer-profile").clone(true);
    container.find("input,select").each(function() {

      if($(this).attr("name")) {

        $(this).attr("name", $(this).attr("name").replace("additionalVolunteersTemplate", "additionalVolunteers_" + newRowIndex));

        if($(this).data("name")) {
          //Because of how we are cloning, you have to use both of these.
          //It is strange but it works.
          $(this).data("name", $(this).data("name").replace("additionalVolunteersTemplate", "additionalVolunteers_" + newRowIndex ));
          $(this).attr("data-name", $(this).data("name").replace("additionalVolunteersTemplate", "additionalVolunteers_" + newRowIndex));
        }

        if($(this).data("target")) {
          //Because of how we are cloning, you have to use both of these.
          //It is strange but it works.
          $(this).data("target", $(this).data("target").replace("additionalVolunteersTemplate", "additionalVolunteers_" + newRowIndex));
          $(this).attr("data-target", $(this).data("target").replace("additionalVolunteersTemplate", "additionalVolunteers_" + newRowIndex));
        }
      }
    });

    //Handle Select2s
    container.find(".crm-select2").crmSelect2();


    //We don't need placeholders on the first row, it looks weird.
    if(newRowIndex === 0) {
      container.find("input").attr("placeholder", "");
    }

    //Hide the form so that it animates down nicely.
    container.hide();
    $("#additionalVolunteers").append(container);
  }






  /*****[ Change the number of additional volunteers ]*****/
  $("#additionalVolunteerQuantity").change(function(event) {

    //We can't add a non-numerical amount of profile forms
    if(!$.isNumeric($(this).val()) && $(this).val() !== '') {
      CRM.alert(ts("Please supply a number"));
      return;
    }

    var numberOfExistingRows = $("#additionalVolunteers .additional-volunteer-profile").length;
    var numberRequested = $(this).val();

    //If we need to add rows, do it.
    if (numberOfExistingRows < numberRequested) {
      var numberOfRowsToAdd = numberRequested - numberOfExistingRows;
      var i = 1;
      while (i <= numberOfRowsToAdd) {
        addProfileRow();
        i++;
      }
    }

    //Show and Hide the Profile rows
    $("#additionalVolunteers .additional-volunteer-profile").slice(0, numberRequested).slideDown();
    $("#additionalVolunteers .additional-volunteer-profile").slice(numberRequested).slideUp();
  });



  /*****[ Setup the Template ]*****/
  //Zero out inputs and removed conflicting IDs
  $(".crm-volunteer-additional-volunteers-template input").val('').removeAttr("id");
  $(".crm-volunteer-additional-volunteers-template select").removeAttr("id");

  //Handle Select2's
  $(".crm-volunteer-additional-volunteers-template select.crm-select2").select2('destroy');


  /*****[ Handle placeholders ]****/
  $('.crm-volunteer-additional-volunteers-template .form-item input[type="text"]').each(function() {
    var labelText = $(this).closest('.form-item').find('.label label').text().replace("*", "").trim();
    $(this).attr("placeholder", labelText);
  });
});