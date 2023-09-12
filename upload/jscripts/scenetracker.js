function certain($id) {
  $post = document.getElementById($id).innerHTML;
  document.getElementById($id).innerHTML = '<form action="" method="post">'
    + '<input type="hidden" value="' + $id + '" name="scene_id" />'
    + '<label for="charas">Charaktername:</label>'
    + '<select id="charas">'
    + '{$users_options_bit}'
    + '</select>'
    + '</form>';
}

$(document).ready(function () {
  $('.scene_edit').click(function () {
    $.ajax({
      //we get our usernames with php. The script gives us a Json. (key value paur -> something like 0: {username: "user1"} 1: {username: "user2"} )
      url: './getusernames.php',
      type: 'get',
      dataType: 'JSON',
      success: function (response) {
        //we need a simple array (not a json) just with all usernames
        var usernames = [];
        //so we get them from response and save them 
        response.forEach(function (user) {
          usernames.push(user.username); //push every name to array we get something like ["user1", "user2", "user3"]
        });
        // new Suggest.LocalMulti("text", "suggest", list, {dispAllKey: true});
        $('#teilnehmer').keydown(function () {
          console.log("teilnehmer");
          new Suggest.LocalMulti("teilnehmer", "suggest", usernames, { dispAllKey: true, delim: "," })
        });
      }
    });
  });
  $('#teilnehmer').click(function () {
    $.ajax({
      //we get our usernames with php. The script gives us a Json. (key value paur -> something like 0: {username: "user1"} 1: {username: "user2"} )
      url: './getusernames.php',
      type: 'get',
      dataType: 'JSON',
      success: function (response) {
        //we need a simple array (not a json) just with all usernames
        var usernames = [];
        //so we get them from response and save them 
        response.forEach(function (user) {
          usernames.push(user.username); //push every name to array we get something like ["user1", "user2", "user3"]
        });
        // new Suggest.LocalMulti("text", "suggest", list, {dispAllKey: true});
        $('#teilnehmer').keydown(function () {
          console.log("teilnehmer");
          new Suggest.LocalMulti("teilnehmer", "suggest", usernames, { dispAllKey: true, delim: "," })
        });
      }
    });
  });
  $('#edit_sceneinfos').click(function () {
    // console.log("submit");
    let id = $("#id").val();
    let place = $("#sceneplace").val();
    let trigger = $("#scenetrigger").val();
    let datetime = $("#scenetracker_date").val() + $("#scenetracker_time").val();
    let user = $("#teilnehmer").val();
    // console.log('place=' + place + '&trigger=' + trigger + '&datetime=' + datetime + ' &user=' + user + ' &user=' + id);

    $.ajax({
      type: 'GET',
      url: 'savescene.php',
      data: 'place=' + place + '&trigger=' + trigger + '&datetime=' + datetime + ' &user=' + user + ' &id=' + id,

      success: function (data) {
        window.location = "showthread.php?tid=" + id;
      },
      error: function (xhr, type, exception) {
        // if ajax fails display error alert
        alert("Irgendwas hat nicht funktioniert. (savescene.php)");
      }
    });
  });

  $('#scenefilter').click(function () {
    // console.log("submit");
    let charakter = $("#charakter").val();
    let uid = $("#uid").val();
    let status = $("#status").val();
    let move = $("#move").val();

    console.log('charakter=' + charakter + '&status=' + status + '&move=' + move);

    $.ajax({
      type: 'GET',
      url: 'scenetrackerfilter.php',
      data: 'charakter=' + charakter + '&status=' + status + '&move=' + move,

      success: function (data) {

      },
      error: function (xhr, type, exception) {
        // if ajax fails display error alert
        alert("Irgendwas hat nicht funktioniert. (scenetrackerfilter.php) ");
      }
    });
  });

});
