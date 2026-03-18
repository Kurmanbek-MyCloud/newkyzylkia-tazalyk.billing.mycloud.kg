$(function(){
		var params = {
			module: "Vtiger",
			action: "GetNotificationsCount"
		};
		app.request.post({data: params}).then(function(err, res){
			var parse = JSON.parse(res);
			if(parse.count != 0) {
				var audio = $("<audio id='notif_audio' autoplay><source src='ding.mp3' type='audio/mpeg'></audio>");
				$("body").append(audio);
				setTimeout(function(){
					audio.remove();
				}, 3000);
				$("#hd_notifs").addClass("hd_notifs_has");
			}
		});
	});