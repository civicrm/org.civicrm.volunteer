CRM.$(function($) {

  function addProfileRow() {
    var newRowIndex = $("#additionalVolunteers .additional-volunteer-profile").length;
    var container = $(".crm-volunteer-additional-volunteers-template .additional-volunteer-profile").clone(true);
    container.find("input,select").each(function() {
      $(this).attr("name", $(this).attr("name").replace("additionalVolunteersTemplate", "additionalVolunteers[" + newRowIndex + "]"));
    });

    if(newRowIndex === 0) {
      container.find("input").attr("placeholder", "");
    }

    container.hide();
    $("#additionalVolunteers").append(container);
  }





  //Change the number of additional volunteers
  $("#additionalVolunteerQuantity").change(function(event) {

    if(!$.isNumeric($(this).val())) {
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



  //Setup the Template
  $(".crm-volunteer-additional-volunteers-template .additional-volunteer-profile input").each(function() {
    //Set the placeholder text
    $(this).attr("placeholder", $(this).closest(".crm-section").find(".label").text().replace("*", ""));
    //change the name so it ends up in a nested array
    if($(this).attr("name").indexOf("[") === -1) {
      $(this).attr("name", "additionalVolunteersTemplate[" + $(this).attr("name") + "]");
    } else {
      $(this).attr("name", "additionalVolunteersTemplate[" + $(this).attr("name").replace("[", "]["));
    }
    //remove conflicting dom ids
    $(this).removeAttr("id").val('');
  });
  $('.crm-volunteer-additional-volunteers-template .additional-volunteer-profile select').removeAttr('checked').removeAttr('selected')


});