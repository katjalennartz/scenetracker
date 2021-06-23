// function certain($id) {
//   $post = document.getElementById($id).innerHTML;
//   document.getElementById($id).innerHTML = '<form action="" method="post">'
//   +'<input type="hidden" value="'+$id+'" name="scene_id" />'
//   +'<input id="certain" type="text" value="username" name="username" autocomplete="off" style="display: block" />'
//   + '<div id="suggest" style="display:none; z-index:10;"></div>'
//   +'<input type="submit" value="speichern" name="saveCertain"/>'
//   +'</form>';
// }

function certain($id) {
  $post = document.getElementById($id).innerHTML;
  document.getElementById($id).innerHTML = '<form action="" method="post">'
  +'<input type="hidden" value="'+$id+'" name="scene_id" />'
  +'<label for="charas">Charaktername:</label>'
  + '<select id="charas">'
  +'{$users_options_bit}'
  +'</select>'
  +'</form>';
}
{/* <label for="cars">Choose a car:</label>

<select id="cars">
  <option value="volvo">Volvo</option>
  <option value="saab">Saab</option>
  <option value="vw">VW</option>
  <option value="audi" selected>Audi</option>
</select> */}
$(document).ready(function(){
  $.ajax({
    //we get our usernames with php. The script gives us a Json. (key value paur -> something like 0: {username: "user1"} 1: {username: "user2"} )
      url: './getusernames.php',
      type: 'get',
      dataType: 'JSON',
      success: function(response){
        //we need a simple array (not a json) just with all usernames
        var usernames = [];
        //so we get them from response and save them 
        response.forEach(function (user) {
          usernames.push(user.username); //push every name to array we get something like ["user1", "user2", "user3"]
        });
        // new Suggest.LocalMulti("text", "suggest", list, {dispAllKey: true});
        $('#teilnehmer').keydown(function () {
          console.log("teilnehmer");
          new Suggest.LocalMulti("teilnehmer", "suggest", usernames, {dispAllKey: true, delim: ","})
        });
      }
  });
});

// window.addEventListener ?
//   window.addEventListener('load', LocalMulti, false) :
//   window.attachEvent('onload', LocalMulti);
