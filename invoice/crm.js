//CRM API
const config = require('./config.json')
const request = require('request')
const md5 = require('md5')
async function getToken() {
    return await new Promise((resolve, reject) => {
        request.get(config.CRM_URL + '?operation=getchallenge&username='+config.crmlogin, function(err,body){
        	if (!err){
        		var ans = JSON.parse(body.body)
        		if (ans.success) resolve(md5(ans.result.token+config.key))
        		else reject(ans.error.message)
        	}
        	else reject(err)
        })
    });
}
async function login() {
    return await new Promise(async (resolve, reject) => {
    	try{
    		var data = {
	            operation: "login",
	            username: config.crmlogin,
	            accessKey: await getToken()
	        }
	        request.post({ url: config.CRM_URL, form: data }, function (err, httpResponse, body) {
	        	if (!err){
	        		var ans = JSON.parse(body)
	        		if (ans.success) resolve(ans.result.sessionName)
	        		else reject(ans.error.message)
	        	}
	        	else reject(err)
	        })
    	}
    	catch(e){
    		reject(e)
    	}
        
    });
}
async function APIcreate(sessionName,moduleName,data) { 
    return await new Promise((resolve, reject) => {
        var element = {
            operation: "create",
            sessionName: sessionName,
            element: JSON.stringify(data),
            elementType: moduleName
        }
        try {
	        request.post({ url: config.CRM_URL, form: element }, function (err, httpResponse, body) {
	        	if (err)
	        		reject(err)
	        	else{
        			var answer = JSON.parse(body)
		            if (answer.success === true)
                        resolve(answer)                        
                    else if(answer.error.message == 'Обнаружено дублирование!')
                        resolve(false)
		            else
                        reject(answer.error.message)
        		} 
	        })	
    	}
    	catch (err) {
			reject(err)
		}
    });
}
async function APIupdate(data) {
    return await new Promise((resolve, reject) => {
        var element = {
            operation: "revise",
            sessionName: sessionName,
            element: JSON.stringify(data)
        }
        try {
	        request.post({ url: config.serverurl, form: element }, function (err, httpResponse, body) {
        		if (body){
        			let answer = JSON.parse(body)
		            if (answer.success === true){
                        // console.log(answer.result.id)
	        			resolve(answer.success)
                    }
		            else{
                        resolve(false)
		                console.error(answer.error.message)
                    }
        		} 
	        })	
    	}
    	catch (err) {
			reject(err)
		}
    });
}
module.exports = {
    login, APIcreate
}