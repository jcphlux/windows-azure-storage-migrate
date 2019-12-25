/**
 * Plugin Template frontend js.
 *
 *  @package WordPress Plugin Template/Runner
 */

jQuery(document).ready(function($) {
  var page = 0;
  var total = 1;
  var nonce = "";

  var callRunner = function(page, nonce) {
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: myAjax.ajaxurl,
      data: {
        action: "windows_azure_storage_migrate_media",
        page: page,
        nonce: nonce
      },
      success: function(response) {
        if (response.type == "success") {
          $("#responce").prepend(response.data + "</br></br>");
          if (page++ <= total) {
            callRunner(page, nonce);
          }
        } else {
          //alert("Your like could not be added");
        }
      }
    });
  };

  $(".azure-migrate-button").click(function(e) {
    e.preventDefault();
    $(".azure-migrate-button").prop("disabled", true);
    total = parseInt($(this).attr("data-total"));
    nonce = $(this).attr("data-nonce");

    callRunner(page, nonce);
  });
});
