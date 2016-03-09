(function(){"use strict";function EventEmitter(){}function indexOfListener(listeners,listener){for(var i=listeners.length;i--;)if(listeners[i].listener===listener)return i;return-1}function alias(name){return function(){return this[name].apply(this,arguments)}}var proto=EventEmitter.prototype,exports=this,originalGlobalValue=exports.EventEmitter;proto.getListeners=function(evt){var response,key,events=this._getEvents();if("object"==typeof evt){response={};for(key in events)events.hasOwnProperty(key)&&evt.test(key)&&(response[key]=events[key])}else response=events[evt]||(events[evt]=[]);return response},proto.flattenListeners=function(listeners){var i,flatListeners=[];for(i=0;i<listeners.length;i+=1)flatListeners.push(listeners[i].listener);return flatListeners},proto.getListenersAsObject=function(evt){var response,listeners=this.getListeners(evt);return listeners instanceof Array&&(response={},response[evt]=listeners),response||listeners},proto.addListener=function(evt,listener){var key,listeners=this.getListenersAsObject(evt),listenerIsWrapped="object"==typeof listener;for(key in listeners)listeners.hasOwnProperty(key)&&-1===indexOfListener(listeners[key],listener)&&listeners[key].push(listenerIsWrapped?listener:{listener:listener,once:!1});return this},proto.on=alias("addListener"),proto.addOnceListener=function(evt,listener){return this.addListener(evt,{listener:listener,once:!0})},proto.once=alias("addOnceListener"),proto.defineEvent=function(evt){return this.getListeners(evt),this},proto.defineEvents=function(evts){for(var i=0;i<evts.length;i+=1)this.defineEvent(evts[i]);return this},proto.removeListener=function(evt,listener){var index,key,listeners=this.getListenersAsObject(evt);for(key in listeners)listeners.hasOwnProperty(key)&&(index=indexOfListener(listeners[key],listener),-1!==index&&listeners[key].splice(index,1));return this},proto.off=alias("removeListener"),proto.addListeners=function(evt,listeners){return this.manipulateListeners(!1,evt,listeners)},proto.removeListeners=function(evt,listeners){return this.manipulateListeners(!0,evt,listeners)},proto.manipulateListeners=function(remove,evt,listeners){var i,value,single=remove?this.removeListener:this.addListener,multiple=remove?this.removeListeners:this.addListeners;if("object"!=typeof evt||evt instanceof RegExp)for(i=listeners.length;i--;)single.call(this,evt,listeners[i]);else for(i in evt)evt.hasOwnProperty(i)&&(value=evt[i])&&("function"==typeof value?single.call(this,i,value):multiple.call(this,i,value));return this},proto.removeEvent=function(evt){var key,type=typeof evt,events=this._getEvents();if("string"===type)delete events[evt];else if("object"===type)for(key in events)events.hasOwnProperty(key)&&evt.test(key)&&delete events[key];else delete this._events;return this},proto.removeAllListeners=alias("removeEvent"),proto.emitEvent=function(evt,args){var listener,i,key,response,listeners=this.getListenersAsObject(evt);for(key in listeners)if(listeners.hasOwnProperty(key))for(i=listeners[key].length;i--;)listener=listeners[key][i],listener.once===!0&&this.removeListener(evt,listener.listener),response=listener.listener.apply(this,args||[]),response===this._getOnceReturnValue()&&this.removeListener(evt,listener.listener);return this},proto.trigger=alias("emitEvent"),proto.emit=function(evt){var args=Array.prototype.slice.call(arguments,1);return this.emitEvent(evt,args)},proto.setOnceReturnValue=function(value){return this._onceReturnValue=value,this},proto._getOnceReturnValue=function(){return this.hasOwnProperty("_onceReturnValue")?this._onceReturnValue:!0},proto._getEvents=function(){return this._events||(this._events={})},EventEmitter.noConflict=function(){return exports.EventEmitter=originalGlobalValue,EventEmitter},"function"==typeof define&&define.amd?define("eventEmitter/EventEmitter",[],function(){return EventEmitter}):"object"==typeof module&&module.exports?module.exports=EventEmitter:this.EventEmitter=EventEmitter}).call(this),function(window){function getIEEvent(obj){var event=window.event;return event.target=event.target||event.srcElement||obj,event}var docElem=document.documentElement,bind=function(){};docElem.addEventListener?bind=function(obj,type,fn){obj.addEventListener(type,fn,!1)}:docElem.attachEvent&&(bind=function(obj,type,fn){obj[type+fn]=fn.handleEvent?function(){var event=getIEEvent(obj);fn.handleEvent.call(fn,event)}:function(){var event=getIEEvent(obj);fn.call(obj,event)},obj.attachEvent("on"+type,obj[type+fn])});var unbind=function(){};docElem.removeEventListener?unbind=function(obj,type,fn){obj.removeEventListener(type,fn,!1)}:docElem.detachEvent&&(unbind=function(obj,type,fn){obj.detachEvent("on"+type,obj[type+fn]);try{delete obj[type+fn]}catch(err){obj[type+fn]=void 0}});var eventie={bind:bind,unbind:unbind};"function"==typeof define&&define.amd?define("eventie/eventie",eventie):window.eventie=eventie}(this),function(window,factory){"use strict";"function"==typeof define&&define.amd?define(["eventEmitter/EventEmitter","eventie/eventie"],function(EventEmitter,eventie){return factory(window,EventEmitter,eventie)}):"object"==typeof module&&module.exports?module.exports=factory(window,require("wolfy87-eventemitter"),require("eventie")):window.imagesLoaded=factory(window,window.EventEmitter,window.eventie)}(window,function(window,EventEmitter,eventie){function extend(a,b){for(var prop in b)a[prop]=b[prop];return a}function isArray(obj){return"[object Array]"==objToString.call(obj)}function makeArray(obj){var ary=[];if(isArray(obj))ary=obj;else if("number"==typeof obj.length)for(var i=0;i<obj.length;i++)ary.push(obj[i]);else ary.push(obj);return ary}function ImagesLoaded(elem,options,onAlways){if(!(this instanceof ImagesLoaded))return new ImagesLoaded(elem,options,onAlways);"string"==typeof elem&&(elem=document.querySelectorAll(elem)),this.elements=makeArray(elem),this.options=extend({},this.options),"function"==typeof options?onAlways=options:extend(this.options,options),onAlways&&this.on("always",onAlways),this.getImages(),$&&(this.jqDeferred=new $.Deferred);var _this=this;setTimeout(function(){_this.check()})}function LoadingImage(img){this.img=img}function Background(url,element){this.url=url,this.element=element,this.img=new Image}var $=window.jQuery,console=window.console,objToString=Object.prototype.toString;ImagesLoaded.prototype=new EventEmitter,ImagesLoaded.prototype.options={},ImagesLoaded.prototype.getImages=function(){this.images=[];for(var i=0;i<this.elements.length;i++){var elem=this.elements[i];this.addElementImages(elem)}},ImagesLoaded.prototype.addElementImages=function(elem){"IMG"==elem.nodeName&&this.addImage(elem),this.options.background===!0&&this.addElementBackgroundImages(elem);var nodeType=elem.nodeType;if(nodeType&&elementNodeTypes[nodeType]){for(var childImgs=elem.querySelectorAll("img"),i=0;i<childImgs.length;i++){var img=childImgs[i];this.addImage(img)}if("string"==typeof this.options.background){var children=elem.querySelectorAll(this.options.background);for(i=0;i<children.length;i++){var child=children[i];this.addElementBackgroundImages(child)}}}};var elementNodeTypes={1:!0,9:!0,11:!0};ImagesLoaded.prototype.addElementBackgroundImages=function(elem){for(var style=getStyle(elem),reURL=/url\(['"]*([^'"\)]+)['"]*\)/gi,matches=reURL.exec(style.backgroundImage);null!==matches;){var url=matches&&matches[1];url&&this.addBackground(url,elem),matches=reURL.exec(style.backgroundImage)}};var getStyle=window.getComputedStyle||function(elem){return elem.currentStyle};return ImagesLoaded.prototype.addImage=function(img){var loadingImage=new LoadingImage(img);this.images.push(loadingImage)},ImagesLoaded.prototype.addBackground=function(url,elem){var background=new Background(url,elem);this.images.push(background)},ImagesLoaded.prototype.check=function(){function onProgress(image,elem,message){setTimeout(function(){_this.progress(image,elem,message)})}var _this=this;if(this.progressedCount=0,this.hasAnyBroken=!1,!this.images.length)return void this.complete();for(var i=0;i<this.images.length;i++){var loadingImage=this.images[i];loadingImage.once("progress",onProgress),loadingImage.check()}},ImagesLoaded.prototype.progress=function(image,elem,message){this.progressedCount++,this.hasAnyBroken=this.hasAnyBroken||!image.isLoaded,this.emit("progress",this,image,elem),this.jqDeferred&&this.jqDeferred.notify&&this.jqDeferred.notify(this,image),this.progressedCount==this.images.length&&this.complete(),this.options.debug&&console&&console.log("progress: "+message,image,elem)},ImagesLoaded.prototype.complete=function(){var eventName=this.hasAnyBroken?"fail":"done";if(this.isComplete=!0,this.emit(eventName,this),this.emit("always",this),this.jqDeferred){var jqMethod=this.hasAnyBroken?"reject":"resolve";this.jqDeferred[jqMethod](this)}},LoadingImage.prototype=new EventEmitter,LoadingImage.prototype.check=function(){var isComplete=this.getIsImageComplete();return isComplete?void this.confirm(0!==this.img.naturalWidth,"naturalWidth"):(this.proxyImage=new Image,eventie.bind(this.proxyImage,"load",this),eventie.bind(this.proxyImage,"error",this),eventie.bind(this.img,"load",this),eventie.bind(this.img,"error",this),void(this.proxyImage.src=this.img.src))},LoadingImage.prototype.getIsImageComplete=function(){return this.img.complete&&void 0!==this.img.naturalWidth},LoadingImage.prototype.confirm=function(isLoaded,message){this.isLoaded=isLoaded,this.emit("progress",this,this.img,message)},LoadingImage.prototype.handleEvent=function(event){var method="on"+event.type;this[method]&&this[method](event)},LoadingImage.prototype.onload=function(){this.confirm(!0,"onload"),this.unbindEvents()},LoadingImage.prototype.onerror=function(){this.confirm(!1,"onerror"),this.unbindEvents()},LoadingImage.prototype.unbindEvents=function(){eventie.unbind(this.proxyImage,"load",this),eventie.unbind(this.proxyImage,"error",this),eventie.unbind(this.img,"load",this),eventie.unbind(this.img,"error",this)},Background.prototype=new LoadingImage,Background.prototype.check=function(){eventie.bind(this.img,"load",this),eventie.bind(this.img,"error",this),this.img.src=this.url;var isComplete=this.getIsImageComplete();isComplete&&(this.confirm(0!==this.img.naturalWidth,"naturalWidth"),this.unbindEvents())},Background.prototype.unbindEvents=function(){eventie.unbind(this.img,"load",this),eventie.unbind(this.img,"error",this)},Background.prototype.confirm=function(isLoaded,message){this.isLoaded=isLoaded,this.emit("progress",this,this.element,message)},ImagesLoaded.makeJQueryPlugin=function(jQuery){jQuery=jQuery||window.jQuery,jQuery&&($=jQuery,$.fn.imagesLoaded=function(options,callback){var instance=new ImagesLoaded(this,options,callback);return instance.jqDeferred.promise($(this))})},ImagesLoaded.makeJQueryPlugin(),ImagesLoaded});