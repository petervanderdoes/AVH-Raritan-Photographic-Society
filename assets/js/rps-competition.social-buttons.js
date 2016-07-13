/* global FB bangoSocial jsmd */
/* eslint no-underscore-dangle: ["error", { "allow": ["_e"] }] */
/* eslint no-console: ["error", { allow: ["log"] }] */
/* social media library */
var rpssocial;
rpssocial = {
  /* store load configuration for each social site's api */
  apiconfig: [],
  /* set analtyics engine for social tracking */
  analytics: 'g',
  /* set analytics for tracking, o for omniture and b for bango */
  setanalytics: function setAnalytics(aID) {
    rpssocial.analytics = aID;
  },
  /* adds tracking param to manual links */
  addurlparam: function addUrlParam(url, param, value) {
    var qstring = /\?.+$/;
    if (qstring.test(url)) {
      return url + '&' + param + '=' + value;
    }
    return url + '?' + param + '=' + value;
  },

  defaulturlparam: function setDefaultUrlParam(givenUrl) {
    var url;
    url = givenUrl;
    url = rpssocial.addurlparam(url, 'utm_campaign', 'general');
    url = rpssocial.addurlparam(url, 'utm_source', 'site');
    return url;
  },
  /* supported social site configurations */
  sites: {
    facebook: {
      buildurl: function buildUrl(tOBJ) {
        var url = rpssocial.defaulturlparam(tOBJ.url);
        url = rpssocial.addurlparam(url, 'utm_medium', 'facebook');
        return 'http://www.facebook.com/share.php?u=' +
          encodeURIComponent(url) +
          '&title=' + encodeURIComponent(tOBJ.title);
      },

      popwidth: 550, popheight: 420, popscroll: 'no',
      loadapi: function loadApi(cb) {
        /* call to load api */
        (function getApi(d, debug) {
          var js;
          var id = 'facebook-jssdk';
          var ref = d.getElementsByTagName('script')[0];
          if (d.getElementById(id)) {
            return;
          }

          js = d.createElement('script');
          js.id = id;
          js.async = true;
          js.src = '//connect.facebook.net/en_US/all' + (debug ? '/debug' : '') + '.js';
          ref.parentNode.insertBefore(js, ref);
        }(document, false));

        window.fbAsyncInit = function initFacebook() {
          FB.init({
            appId: '1509711475952744',
            xfbml: true,
            version: 'v2.1'
          });
        };
      }
    },
    twitter: {
      buildurl: function buildUrl(tOBJ) {
        var url = rpssocial.defaulturlparam(tOBJ.url);
        url = rpssocial.addurlparam(url, 'utm_medium', 'twitter');
        return 'http://twitter.com/intent/tweet?url=' +
          encodeURIComponent(url) +
          '&text=' + encodeURIComponent(tOBJ.title);
      },

      popwidth: 550, popheight: 460, popscroll: 'no',
      loadapi: function loadApi(cb) {
        /* call to load api */
        window.twttr = (function loadTwitter(d, s, id) {
          var t = window.twttr || {};
          var js;
          var fjs = d.getElementsByTagName(s)[0];
          if (d.getElementById(id)) return t;
          js = d.createElement(s);
          js.id = id;
          js.src = '//platform.twitter.com/widgets.js';
          fjs.parentNode.insertBefore(js, fjs);
          t._e = [];
          t.ready = function ready(f) {
            t._e.push(f);
          };

          return t;
        }(document, 'script', 'twitter-wjs'));

        window.twttr.ready(function handleReady(twttr) {
          twttr.events.bind('click', function handleCLick(event) {
            window.rpssocial.share.track({ type: 'click', site: 'twitter' });
          });

          if (typeof cb === 'function') {
            cb.apply();
          }
        });
      }
    },
    googleplus: {
      buildurl: function buildUrl(tOBJ) {
        var url = rpssocial.defaulturlparam(tOBJ.url);
        url = rpssocial.addurlparam(url, 'utm_medium', 'googleplus');
        return 'https://plus.google.com/share?url=' + encodeURIComponent(url) + '&hl=en';
      },

      popwidth: 600, popheight: 600, popscroll: 'yes',
      loadapi: function loadApi(cb) {
        jQuery.getScript('https://apis.google.com/js/plusone.js', function success() {
          if (typeof cb === 'function') {
            cb.apply();
          }
        });
      }
    },
    linkedin: {
      buildurl: function buildUrl(tOBJ) {
        var url = rpssocial.defaulturlparam(tOBJ.url);
        url = rpssocial.addurlparam(url, 'utm_medium', 'linkedin');
        return 'http://www.linkedin.com/shareArticle?mini=true&url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'share_linkedin')) +
          '&title=' + encodeURIComponent(tOBJ.title);
      },

      popwidth: 550, popheight: 570, popscroll: 'no',
      loadapi: function loadApi(cb) {
        jQuery.getScript('http://platform.linkedin.com/in.js', function success() {
          if (typeof cb === 'function') {
            cb.apply();
          }
        });
      }
    },
    email: {
      buildurl: function buildUrl(tOBJ) {
        return 'mailto:?subject=[Raritan Photographic Society] ' +
          tOBJ.title +
          '&body=' +
          tOBJ.title +
          ': ' +
          rpssocial.addurlparam(tOBJ.url, 'sr', 'email');
      },

      popwidth: 1, popheight: 1, popscroll: 'no'
    },
    buffer: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://bufferapp.com/add?text=' +
          encodeURIComponent(tOBJ.title) +
          '&url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'buffer'));
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    digg: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.digg.com/submit?phase=2&url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'digg')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 970, popheight: 500, popscroll: 'yes'
    },
    stumbleupon: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.stumbleupon.com/submit?url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'stumbleupon')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    delicious: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://del.icio.us/post?url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'delicious')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    technorati: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://technorati.com/faves?add=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'technorati')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    posterous: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://posterous.com/share?linkto=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'posterous'));
      },

      popwidth: 1020, popheight: 650, popscroll: 'yes'
    },
    tumblr: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.tumblr.com/share?v=3&u=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'tumblr')) +
          '&t=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    reddit: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.reddit.com/submit?url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'reddit')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    googlebookmarks: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.google.com/bookmarks/mark?op=edit&bkmk=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'googlebookmarks')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    newsvine: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.newsvine.com/_tools/seed&save?u=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'newsvine')) +
          '&h=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 1020, popheight: 500
    },
    'ping.fm': {
      buildurl: function buildUrl(tOBJ) {
        return 'http://ping.fm/ref/?link=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'ping.fm')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    },
    evernote: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.evernote.com/clip.action?url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'evernote')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 980, popheight: 500, popscroll: 'yes'
    },
    friendfeed: {
      buildurl: function buildUrl(tOBJ) {
        return 'http://www.friendfeed.com/share?url=' +
          encodeURIComponent(rpssocial.addurlparam(tOBJ.url, 'sr', 'friendfeed')) +
          '&title=' +
          encodeURIComponent(tOBJ.title);
      },

      popwidth: 750, popheight: 500, popscroll: 'yes'
    }
  },
  /* share library will be defined below for cleaner code */
  share: {},
  /* specify which apis to load and callbacks to call */
  setapiconfig: function setApiConfig(ApiErr) {
    rpssocial.apiconfig = ApiErr;
  },
  /* initialize RPS social library */
  init: function rpsInit() {
    /* load necessary social apis */
    var tArr = rpssocial.apiconfig;
    var i;
    var tApi;
    for (i = 0; i < tArr.length; i++) {
      tApi = tArr[i];
      if (typeof tApi.success === 'function') {
        rpssocial.sites[tApi.site].loadapi(tApi.success);
      } else {
        rpssocial.sites[tApi.site].loadapi();
      }
    }

    /* setup share instances */
    // if (typeof rpssocial.share.data.bars != 'undefined') {
    //    rpssocial.share.init();
    // }
  },

  facebook: function doFacebook(tOBJ) {
    var url = rpssocial.defaulturlparam(tOBJ.url);
    url = rpssocial.addurlparam(url, 'utm_medium', 'facebook');
    FB.ui({
      method: 'share',
      href: url
    }, function handleResponse(response) {
      if (response && !response.error_code) {
        console.log('OK: ' + JSON.stringify(response));
      } else {
        console.log('Not OK: ' + JSON.stringify(response));
      }
    });
  }
};
/* end rpssocial */

/* share library */
rpssocial.share = {
  /* store each instance */
  data: {
    bars: {}
  },
  /* add a single sharebar instance to data */
  addconfig: function addConfig(cObj) {
    rpssocial.share.data.bars[cObj.id] = cObj;
  },
  /* add a multiple config array to data */
  setconfig: function setConfig(cArr) {
    var i;
    /* iterate through and add each one */
    for (i = 0; i < cArr.length; i++) {
      rpssocial.share.addconfig(cArr[i]);
    }
  },
  updatesingleurl: function updateSingleUrl(tId, tUrl, tTitle) {
    var tBar = rpssocial.share.data.bars[tId];
    tBar.url = tUrl;
    tBar.title = tTitle;
    rpssocial.share.data.bars[tId] = tBar;
  },
  /* custom button click function */
  click: function handleClick(tId, tSite) {
    var tSocial;
    var tBar;
    if (window.console) {
      console.log('clicked ' + tSite + ' in: ' + tId);
    }
    /* trigger share actions */
    tBar = rpssocial.share.data.bars[tId];
    tSocial = rpssocial.sites[tSite];
    if (tSite === 'facebook') {
      rpssocial.facebook(tBar);
    } else {
      rpssocial.share.popup(tSocial.buildurl(tBar),
        tSite,
        tSocial.popwidth,
        tSocial.popheight,
        tSocial.popscroll);
    }
    /* track click action */
    // rpssocial.share.track( {'type': 'click', 'site': tSite} );
  },
  /* launch share url in popup window */
  popup: function doPopup(tUrl, tSite, tWidth, tHeight, tScroll) {
    var width = tWidth || 800;
    var height = tHeight || 500;
    /* Position the popup in the middle */
    var px = Math.floor(((screen.availWidth || 1024) - width) / 2);
    var py = Math.floor(((screen.availHeight || 700) - height) / 2);
    return window.open(tUrl, 'rps_pop_' + tSite,
      'width=' + tWidth +
      ',height=' + tHeight +
      ',left=' + px +
      ',top=' + py +
      ',resizable=yes,scrollbars=' + tScroll);
  },
  /* default tracking callback. we would prefer the setting of a tracking callback in shareconfig */
  track: function handleTrack(tOBJ) {
    if (window.console) {
      console.log(tOBJ);
    }

    try {
      if (rpssocial.analytics === 'o') {
        if (jsmd) {
          if (tOBJ.type === 'click') {
            jsmd.trackMetrics('social-click', {
              clickObj: { socialType: tOBJ.site + '_click' }
            });
          } else if (tOBJ.type === 'success') {
            jsmd.trackMetrics('social-click', { clickObj: { socialType: tOBJ.site + '_post' } });
          }
        }
      } else if (rpssocial.analytics === 'b') {
        if (typeof (bangoSocial) === 'function') {
          bangoSocial({ socialMedia: tOBJ.site });
        }
      } else if (rpssocial.analytics === 'g') {
        if (typeof __gaTracker === 'function') {
          if (tOBJ.type === 'ck') {
            __gaTracker('send', {
              hitType: 'social',
              socialNetwork: tOBJ.site,
              socialAction: 'share',
              socialTarget: window.location.href
            });
          }
        }
      }
    } catch (e) {
      if (window.console) {
        console.log('error thrown while registering click tracking. Message - ' + e.message);
      }
    }
  }
  /* start up the sharing */

  // 'init': function () {
  //    var tBars = rpssocial.share.data.bars;
  //    /* interate through each bar instance */
  //    for (var tBar in tBars) {
  //        var tOBJ = tBars[tBar];
  //        var t_cntr = '#' + tOBJ.id + ' .c_sharebar_cntr';
  //        /* remove loading class for container once DOM is ready */
  //        jQuery(t_cntr).removeClass('c_sharebar_loading');
  //    }
  // }
};
/* end rpssocial.share */

// /* default tracking functions for google, linkedin plugin buttons */
// var rpssocial_google_click = function (data) {
//    if (data.state === "on") {
//        window.rpssocial.share.track({'type': 'click', 'site': 'googleplus'});
//    }
//    else if (data.state === "off") {
//    }
// };
// var rpssocial_linkedin_click = function () {
//    window.rpssocial.share.track({'type': 'click', 'site': 'linkedin'});
// };
(function rpsSocialStart($, window, document) {
  $(document).ready(function onReady() {
    'use strict';
    /* initialize rpssocial */
    // rpssocial.init();
    $('.social-button').hover(
      function hoverAdd() {
        $('.share-icon').addClass('active');
      },
      function hoverRemove() {
        $('.share-icon').removeClass('active');
      }
    );
    rpssocial.init();
  });
}(window.jQuery, window, document));

// The global jQuery object is passed as a parameter
