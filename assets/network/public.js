document.addEventListener('error',function(event){
  var img=event.target;
  if(img instanceof HTMLImageElement && img.classList.contains('rtmn-source-portrait')){
    img.hidden=true;
    var fallback=img.nextElementSibling;
    if(fallback && fallback.classList.contains('rtmn-portrait-fallback'))fallback.hidden=false;
  }
},true);
