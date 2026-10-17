(function(){
'use strict';
document.addEventListener('click',function(event){
 var button=event.target.closest('.rtmn-filter-toggle');
 if(!button)return;
 var panel=document.getElementById(button.getAttribute('aria-controls'));
 if(!panel)return;
 var expanded=button.getAttribute('aria-expanded')==='true';
 button.setAttribute('aria-expanded',String(!expanded));
 panel.hidden=expanded;
});
})();

(function(){
'use strict';
function initLabs(){
 document.querySelectorAll('.rtmn-view-labs [data-rtm-labs]').forEach(function(original){
  if(original.dataset.rtmnMulti)return;
  // Replace the scoped DOM to remove the legacy single-theme event handlers.
  var root=original.cloneNode(true);root.dataset.rtmnMulti='1';original.replaceWith(root);
  var toolbar=root.querySelector('.rtm-labs__toolbar'),chips=root.querySelector('.rtm-labs__filters'),search=root.querySelector('.rtm-labs__search input'),empty=root.querySelector('.rtm-labs__empty');
  if(!toolbar||!chips||!search)return;
  var options=Array.from(chips.querySelectorAll('[data-filter]')).map(function(chip){return {value:chip.dataset.filter,label:chip.textContent.trim()};});
  var details=document.createElement('details');details.className='rtmn-lab-filter';
  var summary=document.createElement('summary');summary.textContent='Filters';details.appendChild(summary);
  var panel=document.createElement('fieldset');panel.className='rtmn-lab-filter-menu';
  var legend=document.createElement('legend');legend.textContent='Filter labs by theme';panel.appendChild(legend);
  var inputs=[];
  options.forEach(function(option){
   var label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span');
   input.type='checkbox';input.value=option.value;input.checked=option.value==='all';text.textContent=option.label;
   label.append(input,text);panel.appendChild(label);inputs.push(input);
  });
  details.appendChild(panel);chips.remove();toolbar.appendChild(details);toolbar.classList.add('rtmn-lab-toolbar');
  function apply(){
   var selected=inputs.filter(function(input){return input.checked&&input.value!=='all';}).map(function(input){return input.value;});
   var all=inputs.find(function(input){return input.value==='all';});if(all)all.checked=selected.length===0;
   summary.textContent=selected.length?'Filters · '+selected.length:'Filters';
   var q=search.value.trim().toLowerCase(),any=false;
   root.querySelectorAll('.rtm-labs__group').forEach(function(group){
    var themeMatches=!selected.length||selected.indexOf(group.dataset.theme)!==-1,groupMatches=false;
    group.querySelectorAll('.rtm-lab-card').forEach(function(card){
     var match=themeMatches&&(!q||(card.dataset.search||'').toLowerCase().indexOf(q)!==-1);
     card.style.display=match?'':'none';if(match)groupMatches=true;
    });
    group.style.display=groupMatches?'':'none';if(groupMatches)any=true;
   });
   if(empty)empty.hidden=any;
  }
  inputs.forEach(function(input){input.addEventListener('change',function(){if(input.value==='all')inputs.forEach(function(other){if(other!==input)other.checked=false;});apply();});});
  search.addEventListener('input',apply);
  document.addEventListener('click',function(event){if(!details.contains(event.target))details.open=false;});
  details.addEventListener('keydown',function(event){if(event.key==='Escape'){details.open=false;summary.focus();}});
  apply();
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initLabs);else initLabs();
})();
