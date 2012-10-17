//MyApi Class
//Some features inspired by MooFB
var MyApi = new Class({

	app_id: null,
	FB: null,

	Implements: [Options, Events],

  	options: {
   		langCode: 'en_US',
   		xfbml: true,
   		channelUrl: null
   	},

    initialize: function(app_id,options){
        this.app_id		= app_id;
        this.setOptions(options);

        window.addEvent('domready',function(){
        	this.fbInclude();

        	$$('a[data-fbui]').addEvent('click',function(e){
        		new Event(e).preventDefault();
        		var fbUIjson = JSON.decode($(e.target).getAttribute('data-fbui'));

        		FB.ui(fbUIjson,function(response){
        			var fbUIjson = JSON.decode(this.getAttribute('data-fbui'));
        			var uiURL = new URI(fbUIjson.link);
        			var ogTag = uiURL.getData('ogtags');
        			if (response && response.post_id) {
        				//Success
        				_gaq.push(['_trackEvent', "Social Events", ogTag+' - '+fbUIjson.method+' success' ]);	
					} else {
						//Fail
						_gaq.push(['_trackEvent', "Social Events", ogTag+' - '+fbUIjson.method+' cancelled' ]);	
					}
        		}.bind(this));
        	});
        }.bind(this));
	},

    fbInclude: function(){
    	$$('html')[0].setStyle('display','block');

        var fbRoot = $('fb-root');
		if(typeOf(fbRoot) !== 'element') {
			fbRoot = new Element('div#fb-root');
			fbRoot.inject(document.body);
		}

		var fbJsSrc = document.location.protocol + '//connect.facebook.net/' + this.options.langCode + '/all.js';
		var scriptEl = new Element('script', { async: true, src: fbJsSrc });
		scriptEl.inject(fbRoot,'after');

		//Bound to this
		window.fbAsyncInit = function() {
			this.FB = FB;

			FB._https = (window.location.protocol == "https:");
			FB.init({appId: this.app_id, status: true, cookie: true, xfbml: this.options.xfbml, channelUrl: this.channelUrl, oauth: true, authResponse: true});
			
			FB.Canvas.setAutoGrow(500);
			FB.Canvas.scrollTo(0,0);

			document.fireEvent('fbAsyncInit');

		}.bind(this);
    },

    logoutAction: function(auto){
		window.location = 'index.php?option=com_myapi&task=logout&auto='+auto;
	},
	logout: function(){
		//This logout function allows Joomla users to logout using the fb logout button without throwing a "no session" error is they are not 
		//logged in with facebook
		FB.getLoginStatus(function(response) {
			if (response.session) {
				FB.logout();
				this.logoutAction(0);
			}else{
				this.logoutAction(0);
			}
		}.bind(this));
	},
	fbLogin:function(){
		 FB.login(function(response) {
		 	if (response.authResponse) {
		    	this.joomlaLogin();
		   	}
		}, {scope: $$('.fb-login-button')[0].getAttribute('data-scope')});
	},
	joomlaLogin:function(){
		window.location = 'index.php?option=com_myapi&task=createOrLogin';
	},
	addFriend: function(uid){
		FB.ui({method: 'friends', display: 'popup', id: uid });	
	}
});

//Makes the fbAsyncInit event fire imdeitaley if fbAsyncInit has already been fired
Element.Events.fbAsyncInit = {
    onAdd: function(fn) {
       if(window['FB'] != undefined) {
           fn.call(this);
       }
    }
};