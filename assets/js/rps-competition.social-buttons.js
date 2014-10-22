/* social media library */
var rpssocial = {
    /* store load configuration for each social site's api */
    'apiconfig': [],
    /* set analtyics engine for social tracking */
    'analytics': 'o',
    /* set analytics for tracking, o for omniture and b for bango */
    'setanalytics': function (a_id) {
        rpssocial.analytics = a_id;
    },
    /* adds tracking param to manual links */
    'addurlparam': function (url, param, value) {
        var qstring = /\?.+$/;
        if (qstring.test(url)) {
            return url + '&' + param + '=' + value;
        }
        else {
            return url + '?' + param + '=' + value;
        }
    },
    'defaulurlparam': function (url) {
        url = rpssocial.addurlparam(url, 'utm_campaign', 'general');
        url = rpssocial.addurlparam(url, 'utm_source', 'site');
        return url;
    },
    /* supported social site configurations */
    'sites': {
        'facebook': {
            'buildurl': function (t_obj) {
                var url = rpssocial.defaulurlparam(t_obj.url);
                url = rpssocial.addurlparam(url, 'utm_medium', 'facebook');
                return 'http://www.facebook.com/share.php?u=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 550, 'popheight': 420, 'popscroll': 'no',
            'loadapi': function (cb) {
                /* call to load api */
                (function (d, debug) {
                    var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
                    if (d.getElementById(id)) {
                        return;
                    }
                    js = d.createElement('script');
                    js.id = id;
                    js.async = true;
                    js.src = "//connect.facebook.net/en_US/all" + (debug ? "/debug" : "") + ".js";
                    ref.parentNode.insertBefore(js, ref);
                }(document, /*debug*/ false));
                window.fbAsyncInit = function () {
                    FB.init({
                        appId: '1509711475952744',
                        xfbml: true,
                        version: 'v2.1'
                    });
                };
            }
        },
        'twitter': {
            'buildurl': function (t_obj) {
                var url = rpssocial.defaulurlparam(t_obj.url);
                url = rpssocial.addurlparam(url, 'utm_medium', 'twitter');
                return 'http://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 550, 'popheight': 460, 'popscroll': 'no',
            'loadapi': function (cb) {

                /* call to load api */
                window.twttr = (function (d, s, id) {
                    var t, js, fjs = d.getElementsByTagName(s)[0];
                    if (d.getElementById(id)) return;
                    js = d.createElement(s);
                    js.id = id;
                    js.src = "//platform.twitter.com/widgets.js";
                    fjs.parentNode.insertBefore(js, fjs);
                    return window.twttr || (t = {
                        _e: [], ready: function (f) {
                            t._e.push(f)
                        }
                    });
                }(document, "script", "twitter-wjs"));
                window.twttr.ready(function (twttr) {
                    twttr.events.bind('click', function (event) {
                        window.rpssocial.share.track({'type': 'click', 'site': 'twitter'});
                    });
                    if (typeof cb === 'function') {
                        cb.apply();
                    }
                });
            }
        },
        'googleplus': {
            'buildurl': function (t_obj) {
                var url = rpssocial.defaulurlparam(t_obj.url);
                url = rpssocial.addurlparam(url, 'utm_medium', 'googleplus');
                return 'https://plus.google.com/share?url=' + encodeURIComponent(url) + '&hl=en';
            },
            'popwidth': 600, 'popheight': 600, 'popscroll': 'yes',
            'loadapi': function (cb) {
                jQuery.getScript("https://apis.google.com/js/plusone.js", function success() {
                    if (typeof cb === 'function') {
                        cb.apply();
                    }
                });
            }
        },
        'linkedin': {
            'buildurl': function (t_obj) {
                return 'http://www.linkedin.com/shareArticle?mini=true&url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'share_linkedin')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 550, 'popheight': 570, 'popscroll': 'no',
            'loadapi': function (cb) {
                jQuery.getScript("http://platform.linkedin.com/in.js", function success() {
                    if (typeof cb === 'function') {
                        cb.apply();
                    }
                });
            }
        },
        'email': {
            'buildurl': function (t_obj) {
                return 'mailto:?subject=[Raritan Photographic Society] ' + t_obj.title + '&body=' + t_obj.title + ': ' + rpssocial.addurlparam(t_obj.url, 'sr', 'email');
            },
            'popwidth': 1, 'popheight': 1, 'popscroll': 'no'
        },
        'digg': {
            'buildurl': function (t_obj) {
                return 'http://www.digg.com/submit?phase=2&url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'digg')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 970, 'popheight': 500, 'popscroll': 'yes'
        },
        'stumbleupon': {
            'buildurl': function (t_obj) {
                return 'http://www.stumbleupon.com/submit?url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'stumbleupon')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'delicious': {
            'buildurl': function (t_obj) {
                return 'http://del.icio.us/post?url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'delicious')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'technorati': {
            'buildurl': function (t_obj) {
                return 'http://technorati.com/faves?add=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'technorati')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'posterous': {
            'buildurl': function (t_obj) {
                return 'http://posterous.com/share?linkto=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'posterous'));
            },
            'popwidth': 1020, 'popheight': 650, 'popscroll': 'yes'
        },
        'tumblr': {
            'buildurl': function (t_obj) {
                return 'http://www.tumblr.com/share?v=3&u=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'tumblr')) + '&t=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'reddit': {
            'buildurl': function (t_obj) {
                return 'http://www.reddit.com/submit?url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'reddit')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'googlebookmarks': {
            'buildurl': function (t_obj) {
                return 'http://www.google.com/bookmarks/mark?op=edit&bkmk=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'googlebookmarks')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'newsvine': {
            'buildurl': function (t_obj) {
                return 'http://www.newsvine.com/_tools/seed&save?u=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'newsvine')) + '&h=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 1020, 'popheight': 500
        },
        'ping.fm': {
            'buildurl': function (t_obj) {
                return 'http://ping.fm/ref/?link=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'ping.fm')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        },
        'evernote': {
            'buildurl': function (t_obj) {
                return 'http://www.evernote.com/clip.action?url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'evernote')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 980, 'popheight': 500, 'popscroll': 'yes'
        },
        'friendfeed': {
            'buildurl': function (t_obj) {
                return 'http://www.friendfeed.com/share?url=' + encodeURIComponent(rpssocial.addurlparam(t_obj.url, 'sr', 'friendfeed')) + '&title=' + encodeURIComponent(t_obj.title);
            },
            'popwidth': 750, 'popheight': 500, 'popscroll': 'yes'
        }
    },
    /* share library will be defined below for cleaner code */
    'share': {},
    /* specify which apis to load and callbacks to call */
    'setapiconfig': function (api_arr) {
        rpssocial.apiconfig = api_arr;
    },
    /* initialize RPS social library */
    'init': function () {

        /* load necessary social apis */
        var t_arr = rpssocial.apiconfig;
        for (var i = 0; i < t_arr.length; i++) {
            var t_api = t_arr[i];
            if (typeof t_api.success === 'function') {
                rpssocial.sites[t_api.site].loadapi(t_api.success);
            }
            else {
                rpssocial.sites[t_api.site].loadapi();
            }
        }
        ///* setup share instances */
        //if (typeof rpssocial.share.data.bars != 'undefined') {
        //    rpssocial.share.init();
        //}
    },
    'facebook': function (t_obj) {
        var url = rpssocial.defaulurlparam(t_obj.url);
        url = rpssocial.addurlparam(url, 'utm_medium', 'facebook');
        FB.ui({
            method: 'share',
            href: url
        }, function (response) {
        });
    }
};
/* end rpssocial */

/* share library */
rpssocial.share = {
    /* store each instance */
    'data': {
        'bars': {}
    },
    /* add a single sharebar instance to data */
    'addconfig': function (c_obj) {
        rpssocial.share.data.bars[c_obj.id] = c_obj;
    },
    /* add a multiple config array to data */
    'setconfig': function (c_arr) {

        /* iterate through and add each one */
        for (var i = 0; i < c_arr.length; i++) {
            rpssocial.share.addconfig(c_arr[i]);
        }
    },
    /* update the url for all share instances - for dynamic video/ajax-y pages */
    'updateurl': function (t_url) {

        //var t_bar = rpssocial.share.data.bars[t_id]
    },
    'updatesingleurl': function (t_id, t_url, t_title) {
        var t_bar = rpssocial.share.data.bars[t_id];
        t_bar.url = t_url;
        t_bar.title = t_title;
        rpssocial.share.data.bars[t_id] = t_bar;
    },
    /* custom button click function */
    'click': function (t_id, t_site) {
        if (window.console) {
            console.log('clicked ' + t_site + ' in: ' + t_id);
        }
        /* trigger share actions */
        var t_bar = rpssocial.share.data.bars[t_id];
        var t_social = rpssocial.sites[t_site];
        if (t_site == 'facebook') {
            rpssocial.facebook(t_bar);
        } else {
            rpssocial.share.popup(t_social.buildurl(t_bar), t_site, t_social.popwidth, t_social.popheight, t_social.popscroll);
        }
        /* track click action */
        //rpssocial.share.track({'type': 'click', 'site': t_site});
    },
    /* launch share url in popup window */
    'popup': function (t_url, t_site, t_width, t_height, t_scroll) {
        var width = t_width || 800;
        var height = t_height || 500;
        /* Position the popup in the middle */
        var px = Math.floor(((screen.availWidth || 1024) - width) / 2);
        var py = Math.floor(((screen.availHeight || 700) - height) / 2);
        return window.open(t_url, 'rps_pop_' + t_site, 'width=' + t_width + ',height=' + t_height + ',left=' + px + ',top=' + py + ',resizable=yes,scrollbars=' + t_scroll);
    },
    /* default tracking callback but we would prefer the setting of a tracking callback in shareconfig */
    'track': function (t_obj) {
        if (window.console) {
            console.log(t_obj);
        }
        try {
            if (rpssocial.analytics === 'o') {
                if (jsmd) {
                    if (t_obj.type === 'click') {
                        jsmd.trackMetrics("social-click", {'clickObj': {'socialType': t_obj.site + '_click'}});
                    }
                    else if (t_obj.type === 'success') {
                        jsmd.trackMetrics("social-click", {'clickObj': {'socialType': t_obj.site + '_post'}});
                    }
                }
            }
            else if (rpssocial.analytics === 'b') {
                if (typeof(bangoSocial) === 'function') {
                    bangoSocial({'socialMedia': t_obj.site});
                }
            }
        }
        catch (e) {
            if (window.console) {
                console.log("error thrown while registering click tracking. Message - " + e.message);
            }
        }
    }
    /* start up the sharing */
    //'init': function () {
    //    var t_bars = rpssocial.share.data.bars;
    //    /* interate through each bar instance */
    //    for (var t_bar in t_bars) {
    //        var t_obj = t_bars[t_bar];
    //        var t_cntr = '#' + t_obj.id + ' .c_sharebar_cntr';
    //        /* remove loading class for container once DOM is ready */
    //        jQuery(t_cntr).removeClass('c_sharebar_loading');
    //    }
    //}
};
/* end rpssocial.share */

///* default tracking functions for google, linkedin plugin buttons */
//var rpssocial_google_click = function (data) {
//    if (data.state === "on") {
//        window.rpssocial.share.track({'type': 'click', 'site': 'googleplus'});
//    }
//    else if (data.state === "off") {
//    }
//};
//var rpssocial_linkedin_click = function () {
//    window.rpssocial.share.track({'type': 'click', 'site': 'linkedin'});
//};
(function ($, window, document) {
    $(document).ready(function () {
        'use strict';
        /* initialize rpssocial */
        //rpssocial.init();
        $(".social-button").hover(
            function () {
                $(".share-icon").addClass("active");
            }, function () {
                $(".share-icon").removeClass("active");
            }
        );
        rpssocial.init();
    });
}(window.jQuery, window, document));
// The global jQuery object is passed as a parameter
