$(function(){
	
	if(_META.view == "Edit") {
		
		var wrapper = $("<div id='mentions_wrp'></div>");
		var menu = $("<ul id='mentions_menu'></ul>");
		var empty = $("<h3 id='mentions_empty'>Результатов не найдено</h3>")
		var isMentionMode = false;
		var input = null;
		var target = $("input[type='text']:not(input[name='email']),textarea");
		var form = $("#EditView");
		app.mentionSelect = function(obj) {
			obj = $(obj);
			var values = {};
			var localValues = {};
			var id = parseInt(obj.data("id"));
			var field = form.find("input#mentions_values[type='hidden']");
			if(field.length) values = JSON.parse(field.val());
			if(values[id] == undefined) values[id] = $.trim(obj.text());
			if(!field.length) {
				var el = $("<input type='hidden' name='mentions' id='mentions_values'>");
				el.val(JSON.stringify(values));
				form.append(el);
			}
			else field.val(JSON.stringify(values));
			var put = input.val();
			var pos = put.lastIndexOf("@") + 1;
			var info = input.parent().data("info");
			if(info != undefined) localValues = JSON.parse(info);
			input.val(put.substr(0, pos) + $.trim(obj.text()));
			isMentionMode = false;
			input.parent().find("#mentions_wrp").remove();
			input.parent().removeClass("mentions_parent");
			localValues[input.attr("name")] = {
				"text" : $.trim(obj.text()),
				"id" : id
			};
			input.parent().attr("data-info", JSON.stringify(localValues));
		};
		var loadOptions = function(val) {
			var params = {
				module: "Vtiger",
				action: "GetMentions"
			};
			if(val !== undefined) params.candidate = val;
			app.request.post({data: params}).then(function(e, res){
				res = JSON.parse(res);
				if(!res.status) {
					if(!wrapper.find("#mentions_empty").length) wrapper.append(empty);
					if(wrapper.find("#mentions_menu").length) wrapper.find("#mentions_menu").remove();
				}
				else {
					if(wrapper.find("#mentions_empty").length) wrapper.find("#mentions_empty").remove();
					if(!wrapper.find("#mentions_menu").length) wrapper.append(menu);
					menu.empty();
					var opts = "";
					for(var i in res.data) opts += "<li data-id='"+ i +"' onclick='return app.mentionSelect(this)'>"+ res.data[i] +"</li>";
					menu.append(opts);
				}
			});
		};
		
		form.find("*").on("click", function(e){
			if(!wrapper.is(e.target) && !$(e.target).closest(wrapper).length) {
				var parent = $(this).parent();
				if(parent.find("#mentions_wrp").length) {
					parent.find("#mentions_wrp").remove();
					parent.removeClass("mentions_parent");
					isMentionMode = false;
				}
				input = null;
			}
		});
		
		target.on("keyup", function(e){
			var cval = $(this).val();
			var name = $(this).attr("name");
			if(e.keyCode == 50 && e.shiftKey) {
				//if @ was entered
				var parent = $(this).parent();
				if(!parent.find("#mentions_wrp").length) {
					parent.addClass("mentions_parent");
					parent.append(wrapper);
				}
				isMentionMode = true;
			}
			else if(e.keyCode == 32) {
				isMentionMode = false;
				if(input != null) {
					input.parent().removeClass("mentions_parent");
					input.parent().find("#mentions_wrp").remove();
				}
			}
			
			if(isMentionMode) {
				input = $(this);
				var cval = $(this).val();
				var from = cval.lastIndexOf("@");
				var value = from != cval.length - 1 ? cval.substr(from + 1) : "";
				loadOptions(value);
			}
			else input = null;
			
			if(input != null && input.parent().data("info") !== undefined) {
				var info = JSON.parse(input.parent().data("info"));
				if(info[input.attr("name")] !== undefined) {
					var data = info[input.attr("name")];
					if(input.val().indexOf("@" + data.text) < 0) {
						var field = form.find("input#mentions_values[type='hidden']");
						var values = JSON.parse(field.val());
						console.log(values);
					}
				}
			}
			
		});
		
	}
	
});