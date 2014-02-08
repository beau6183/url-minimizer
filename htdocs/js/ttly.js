$(document).ready(function(){
    $('#homeform').submit(function(e) {
        $.ajax({
            type: "POST",
            url: "",
            data: $('#homeform').serialize(),
            success: function(rsp) {
                if (rsp.error) {
                    
                          alert(rsp.error.description);
                          
                    // switch(rsp.error.code) {
                        // case 1:
                        // case 2:
                        // case 3:
                          // break;
                        // case 4:
                          // $('#uri').setCustomValidity("Link is invalid!");
                          // $('#uri').change(function(){$('#uri').setCustomValidity("")});
                          // break;
                        // default:
                          // alert(rsp.error.description);
                          // break;
                    // }
                    return false;
                }
                else {
                    $('#home').hide();
                    $('#resultcontainer').html("<h2><a href=\""+rsp.requestedUrl+"\" target=\"_blank\">" 
                                               + rsp.requestedUrl 
                                               + "</a><span class=\"sub\">(right-click, copy link address)</span></h2><br/><br/><br/>"
                                               + "<br/><input type=\"submit\" value=\"Make Another\" onclick=\"resetIt(); return false;\" />")
                                        .fadeIn(1500);
                }
            },
            error: function() {
                alert('An error occured');
            }
       });
       return false;
    });
    $('#uri').click(function(){
        if ($('#uri').val().match(/^\s*$/)) {
            $('#uri').val('http://');
        }
    });
    $('#uri').blur(function(){
        if ($('#uri').val().match(/^\s*\w+\:\/\/\s*$/)) {
            $('#uri').val(null);
        } 
    });
  
});

function resetIt() {
    $('#resultcontainer').hide(function(){
        $('#uri').val(null);
        $('#d').val(null);
        $('#home').fadeIn(1500);
    });
}