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
        console.log(usernames)
        $('#text').keydown(function () {
          new Suggest.LocalMulti(
              "text", // input element id.
              "suggest", // suggestion area id.
              usernames, // suggest candidates list
              {dispAllKey: true} //we want to add more than one name 
              //more examples for configuration: http://www.enjoyxstudy.com/javascript/suggest/index.en.html
              ); // options
      });
      }
  });
});


function certain($tid) {
  $tid = 'p' + $tid;
  $post = document.getElementById($pid).innerHTML;
  document.getElementById($pid).innerHTML = '<form action="" method="post">'
  + '<textarea name ="editPost" class='+$pid+' id='+$pid+'>'+$post+'</textarea><br />'
  +'<input type="hidden" value="'+$id+'" name="sn_postEditId" />'
  +'<input type="date" value="'+$date+'" name="sn_postDatumEdit" />'
  +'<input type="time" name="sn_postUhrzeitEdit" value="'+$time+'" /></br>'
  +'<input type="submit" value="speichern" name="saveEditPost"/>'
  +'<input type="button" value="abbrechen" onclick="abort(' + $id + ',\'' + escape($post) + '\')"/></form>';
}