(function(){function EventEmitter(){}function indexOfListener(listeners,listener){for(var i=listeners.length;i--;)if(listeners[i].listener===listener)return i;return-1}function alias(name){return function(){return this[name].apply(this,arguments)}}var proto=EventEmitter.prototype,exports=this,originalGlobalValue=exports.EventEmitter;proto.getListeners=function(evt){var response,key,events=this._getEvents();if("object"==typeof evt){response={};for(key in events)events.hasOwnProperty(key)&&evt.test(key)&&(response[key]=events[key])}else response=events[evt]||(events[evt]=[]);return response},proto.flattenListeners=function(listeners){var i,flatListeners=[];for(i=0;i<listeners.length;i+=1)flatListeners.push(listeners[i].listener);return flatListeners},proto.getListenersAsObject=function(evt){var response,listeners=this.getListeners(evt);return listeners instanceof Array&&(response={},response[evt]=listeners),response||listeners},proto.addListener=function(evt,listener){var key,listeners=this.getListenersAsObject(evt),listenerIsWrapped="object"==typeof listener;for(key in listeners)listeners.hasOwnProperty(key)&&-1===indexOfListener(listeners[key],listener)&&listeners[key].push(listenerIsWrapped?listener:{listener:listener,once:!1});return this},proto.on=alias("addListener"),proto.addOnceListener=function(evt,listener){return this.addListener(evt,{listener:listener,once:!0})},proto.once=alias("addOnceListener"),proto.defineEvent=function(evt){return this.getListeners(evt),this},proto.defineEvents=function(evts){for(var i=0;i<evts.length;i+=1)this.defineEvent(evts[i]);return this},proto.removeListener=function(evt,listener){var index,key,listeners=this.getListenersAsObject(evt);for(key in listeners)listeners.hasOwnProperty(key)&&(index=indexOfListener(listeners[key],listener),-1!==index&&listeners[key].splice(index,1));return this},proto.off=alias("removeListener"),proto.addListeners=function(evt,listeners){return this.manipulateListeners(!1,evt,listeners)},proto.removeListeners=function(evt,listeners){return this.manipulateListeners(!0,evt,listeners)},proto.manipulateListeners=function(remove,evt,listeners){var i,value,single=remove?this.removeListener:this.addListener,multiple=remove?this.removeListeners:this.addListeners;if("object"!=typeof evt||evt instanceof RegExp)for(i=listeners.length;i--;)single.call(this,evt,listeners[i]);else for(i in evt)evt.hasOwnProperty(i)&&(value=evt[i])&&("function"==typeof value?single.call(this,i,value):multiple.call(this,i,value));return this},proto.removeEvent=function(evt){var key,type=typeof evt,events=this._getEvents();if("string"===type)delete events[evt];else if("object"===type)for(key in events)events.hasOwnProperty(key)&&evt.test(key)&&delete events[key];else delete this._events;return this},proto.removeAllListeners=alias("removeEvent"),proto.emitEvent=function(evt,args){var listener,i,key,response,listeners=this.getListenersAsObject(evt);for(key in listeners)if(listeners.hasOwnProperty(key))for(i=listeners[key].length;i--;)listener=listeners[key][i],listener.once===!0&&this.removeListener(evt,listener.listener),response=listener.listener.apply(this,args||[]),response===this._getOnceReturnValue()&&this.removeListener(evt,listener.listener);return this},proto.trigger=alias("emitEvent"),proto.emit=function(evt){var args=Array.prototype.slice.call(arguments,1);return this.emitEvent(evt,args)},proto.setOnceReturnValue=function(value){return this._onceReturnValue=value,this},proto._getOnceReturnValue=function(){return this.hasOwnProperty("_onceReturnValue")?this._onceReturnValue:!0},proto._getEvents=function(){return this._events||(this._events={})},EventEmitter.noConflict=function(){return exports.EventEmitter=originalGlobalValue,EventEmitter},"function"==typeof define&&define.amd?define("eventEmitter/EventEmitter",[],function(){return EventEmitter}):"object"==typeof module&&module.exports?module.exports=EventEmitter:this.EventEmitter=EventEmitter}).call(this),function(window){function getIEEvent(obj){var event=window.event;return event.target=event.target||event.srcElement||obj,event}var docElem=document.documentElement,bind=function(){};docElem.addEventListener?bind=function(obj,type,fn){obj.addEventListener(type,fn,!1)}:docElem.attachEvent&&(bind=function(obj,type,fn){obj[type+fn]=fn.handleEvent?function(){var event=getIEEvent(obj);fn.handleEvent.call(fn,event)}:function(){var event=getIEEvent(obj);fn.call(obj,event)},obj.attachEvent("on"+type,obj[type+fn])});var unbind=function(){};docElem.removeEventListener?unbind=function(obj,type,fn){obj.removeEventListener(type,fn,!1)}:docElem.detachEvent&&(unbind=function(obj,type,fn){obj.detachEvent("on"+type,obj[type+fn]);try{delete obj[type+fn]}catch(err){obj[type+fn]=void 0}});var eventie={bind:bind,unbind:unbind};"function"==typeof define&&define.amd?define("eventie/eventie",eventie):window.eventie=eventie}(this),function(window,factory){"function"==typeof define&&define.amd?define(["eventEmitter/EventEmitter","eventie/eventie"],function(EventEmitter,eventie){return factory(window,EventEmitter,eventie)}):"object"==typeof exports?module.exports=factory(window,require("wolfy87-eventemitter"),require("eventie")):window.imagesLoaded=factory(window,window.EventEmitter,window.eventie)}(window,function(window,EventEmitter,eventie){function extend(a,b){for(var prop in b)a[prop]=b[prop];return a}function isArray(obj){return"[object Array]"===objToString.call(obj)}function makeArray(obj){var ary=[];if(isArray(obj))ary=obj;else if("number"==typeof obj.length)for(var i=0,len=obj.length;len>i;i++)ary.push(obj[i]);else ary.push(obj);return ary}function ImagesLoaded(elem,options,onAlways){if(!(this instanceof ImagesLoaded))return new ImagesLoaded(elem,options);"string"==typeof elem&&(elem=document.querySelectorAll(elem)),this.elements=makeArray(elem),this.options=extend({},this.options),"function"==typeof options?onAlways=options:extend(this.options,options),onAlways&&this.on("always",onAlways),this.getImages(),$&&(this.jqDeferred=new $.Deferred);var _this=this;setTimeout(function(){_this.check()})}function LoadingImage(img){this.img=img}function Resource(src){this.src=src,cache[src]=this}var $=window.jQuery,console=window.console,hasConsole="undefined"!=typeof console,objToString=Object.prototype.toString;ImagesLoaded.prototype=new EventEmitter,ImagesLoaded.prototype.options={},ImagesLoaded.prototype.getImages=function(){this.images=[];for(var i=0,len=this.elements.length;len>i;i++){var elem=this.elements[i];"IMG"===elem.nodeName&&this.addImage(elem);var nodeType=elem.nodeType;if(nodeType&&(1===nodeType||9===nodeType||11===nodeType))for(var childElems=elem.querySelectorAll("img"),j=0,jLen=childElems.length;jLen>j;j++){var img=childElems[j];this.addImage(img)}}},ImagesLoaded.prototype.addImage=function(img){var loadingImage=new LoadingImage(img);this.images.push(loadingImage)},ImagesLoaded.prototype.check=function(){function onConfirm(image,message){return _this.options.debug&&hasConsole&&console.log("confirm",image,message),_this.progress(image),checkedCount++,checkedCount===length&&_this.complete(),!0}var _this=this,checkedCount=0,length=this.images.length;if(this.hasAnyBroken=!1,!length)return void this.complete();for(var i=0;length>i;i++){var loadingImage=this.images[i];loadingImage.on("confirm",onConfirm),loadingImage.check()}},ImagesLoaded.prototype.progress=function(image){this.hasAnyBroken=this.hasAnyBroken||!image.isLoaded;var _this=this;setTimeout(function(){_this.emit("progress",_this,image),_this.jqDeferred&&_this.jqDeferred.notify&&_this.jqDeferred.notify(_this,image)})},ImagesLoaded.prototype.complete=function(){var eventName=this.hasAnyBroken?"fail":"done";this.isComplete=!0;var _this=this;setTimeout(function(){if(_this.emit(eventName,_this),_this.emit("always",_this),_this.jqDeferred){var jqMethod=_this.hasAnyBroken?"reject":"resolve";_this.jqDeferred[jqMethod](_this)}})},$&&($.fn.imagesLoaded=function(options,callback){var instance=new ImagesLoaded(this,options,callback);return instance.jqDeferred.promise($(this))}),LoadingImage.prototype=new EventEmitter,LoadingImage.prototype.check=function(){var resource=cache[this.img.src]||new Resource(this.img.src);if(resource.isConfirmed)return void this.confirm(resource.isLoaded,"cached was confirmed");if(this.img.complete&&void 0!==this.img.naturalWidth)return void this.confirm(0!==this.img.naturalWidth,"naturalWidth");var _this=this;resource.on("confirm",function(resrc,message){return _this.confirm(resrc.isLoaded,message),!0}),resource.check()},LoadingImage.prototype.confirm=function(isLoaded,message){this.isLoaded=isLoaded,this.emit("confirm",this,message)};var cache={};return Resource.prototype=new EventEmitter,Resource.prototype.check=function(){if(!this.isChecked){var proxyImage=new Image;eventie.bind(proxyImage,"load",this),eventie.bind(proxyImage,"error",this),proxyImage.src=this.src,this.isChecked=!0}},Resource.prototype.handleEvent=function(event){var method="on"+event.type;this[method]&&this[method](event)},Resource.prototype.onload=function(event){this.confirm(!0,"onload"),this.unbindProxyEvents(event)},Resource.prototype.onerror=function(event){this.confirm(!1,"onerror"),this.unbindProxyEvents(event)},Resource.prototype.confirm=function(isLoaded,message){this.isConfirmed=!0,this.isLoaded=isLoaded,this.emit("confirm",this,message)},Resource.prototype.unbindProxyEvents=function(event){eventie.unbind(event.target,"load",this),eventie.unbind(event.target,"error",this)},ImagesLoaded});