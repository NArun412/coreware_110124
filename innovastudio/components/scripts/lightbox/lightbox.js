var Lightbox = (function () {
   'use strict';

   class Dom {
     createElement(tag) {
       return document.createElement(tag);
     }

     appendChild(parent, child) {
       if (parent) parent.appendChild(child);
     }

     appendHtml(parent, html) {
       if (parent) parent.insertAdjacentHTML('beforeend', html);
     }

     addEventListener(parent, type, listener) {
       if (parent) parent.addEventListener(type, listener);
     }

     addClass(element, classname) {
       if (!element) return;
       if (this.hasClass(element, classname)) return;
       if (element.classList.length === 0) element.className = classname;else element.className = element.className + ' ' + classname;
       element.className = element.className.replace(/  +/g, ' '); //else element.classList.add(classname); //error if there is -
     }

     removeClass(element, classname) {
       if (!element) return;

       if (element.classList.length > 0) {
         // element.className = element.className.replace(new RegExp('\\b'+ classname+'\\b', 'g'), '');
         // element.className = element.className.replace(/  +/g, ' ');
         let i, j, imax, jmax;
         let classesToDel = classname.split(' ');

         for (i = 0, imax = classesToDel.length; i < imax; ++i) {
           if (!classesToDel[i]) continue;
           let classtoDel = classesToDel[i]; // https://jsperf.com/removeclass-methods 

           let sClassName = '';
           let currentClasses = element.className.split(' ');

           for (j = 0, jmax = currentClasses.length; j < jmax; ++j) {
             if (!currentClasses[j]) continue;
             if (currentClasses[j] !== classtoDel) sClassName += currentClasses[j] + ' ';
           }

           element.className = sClassName.trim();
         }

         if (element.className === '') element.removeAttribute('class');
       }
     } // https://plainjs.com/javascript/attributes/adding-removing-and-testing-for-classes-9/
     // addClass(element, classname) {
     //     console.log(element.classList)
     //     if (element.classList) element.classList.add(classname);
     //     else if (!this.hasClass(element, classname)) element.className += ' ' + classname;
     // }
     // removeClass(element, classname) {
     //     if (element.classList) element.classList.remove(classname);
     //     else element.className = element.className.replace(new RegExp('\\b'+ classname+'\\b', 'g'), '');
     // }


     hasClass(element, classname) {
       if (!element) return false;

       try {
         let s = element.getAttribute('class');
         return new RegExp('\\b' + classname + '\\b').test(s);
       } catch (e) {// Do Nothing
         // console.log(element);
       } //return element.classList ? element.classList.contains(classname) : new RegExp('\\b'+ classname+'\\b').test(element.className);

     }

     moveAfter(element, targetElement) {
       targetElement.parentNode.insertBefore(element, targetElement);
       targetElement.parentNode.insertBefore(targetElement, targetElement.previousElementSibling);
     } // https://stackoverflow.com/questions/10381296/best-way-to-get-child-nodes


     elementChildren(element) {
       const childNodes = element.childNodes;
       let children = [];
       let i = childNodes.length;

       while (i--) {
         if (childNodes[i].nodeType === 1
         /*&& childNodes[i].tagName === 'DIV'*/
         ) {
           children.unshift(childNodes[i]);
         }
       }

       return children;
     }

     parentsHasClass(element, classname) {
       while (element) {
         // if(classname==='is-side') console.log(element.nodeName); // NOTE: click on svg can still returns undefined in IE11
         if (!element.tagName) return false;
         if (element.tagName === 'BODY' || element.tagName === 'HTML') return false; // if(!element.classList) {
         //     console.log('no classList');
         //     return false;
         // }

         if (this.hasClass(element, classname)) {
           return true;
         } // TODO: if(element.nodeName.toLowerCase() === 'svg') console.log(element);


         element = element.parentNode;
       }
     }

     parentsHasId(element, id) {
       while (element) {
         if (!element.tagName) return false;
         if (element.tagName === 'BODY' || element.tagName === 'HTML') return false;

         if (element.id === id) {
           return true;
         }

         element = element.parentNode;
       }
     }

     parentsHasTag(element, tagname) {
       while (element) {
         if (!element.tagName) return false;
         if (element.tagName === 'BODY' || element.tagName === 'HTML') return false;

         if (element.tagName.toLowerCase() === tagname.toLowerCase()) {
           return true;
         }

         element = element.parentNode;
       }
     }

     parentsHasAttribute(element, attrname) {
       while (element) {
         if (!element.tagName) return false;
         if (element.tagName === 'BODY' || element.tagName === 'HTML') return false;

         try {
           if (element.hasAttribute(attrname)) {
             // error on svg element
             return true;
           }
         } catch (e) {// Do Nothing
           // console.log(element);
           // return false;
         }

         element = element.parentNode;
       }
     }

     parentsHasElement(element, tagname) {
       while (element) {
         if (!element.tagName) return false;
         if (element.tagName === 'BODY' || element.tagName === 'HTML') return false;
         element = element.parentNode;
         if (!element) return false;
         if (!element.tagName) return false;

         if (element.tagName.toLowerCase() === tagname) {
           return true;
         }
       }
     }

     removeClasses(elms, classname) {
       for (let i = 0; i < elms.length; i++) {
         elms[i].classList.remove(classname);
       }
     }

     removeAttributes(elms, attrname) {
       for (let i = 0; i < elms.length; i++) {
         elms[i].removeAttribute(attrname);
       }
     }

     removeElements(elms) {
       Array.prototype.forEach.call(elms, el => {
         el.parentNode.removeChild(el);
       });
     }

     getStyle(element, property) {
       return window.getComputedStyle ? window.getComputedStyle(element, null).getPropertyValue(property) : element.style[property.replace(/-([a-z])/g, function (g) {
         return g[1].toUpperCase();
       })];
     }

   }

   const dom = new Dom();

   class Lightbox {
     constructor() {
       let builderStuff = document.querySelector('#_cbhtml'); // All editing controls will be placed within <div id="_cbhtml">...</div>

       if (!builderStuff) {
         builderStuff = dom.createElement('div');
         builderStuff.id = '_cbhtml';
         builderStuff.className = 'is-ui';
         dom.appendChild(document.body, builderStuff);
       }

       this.builderStuff = builderStuff;
       let div = this.builderStuff.querySelector('#_lightbox');

       if (!div) {
         let html = `
                <div class="is-lightbox lightbox-externalvideo">
                    <button class="cmd-lightbox-close" title="Close" type="button" style="flex:none;position:absolute;top:0;right:0;background:none;z-index:1;">
                        <svg><use xlink:href="#icon-close"></use></svg>
                    </button>
                    <div class="lightbox-content" style="width:100%;">
                        <div class="embed-responsive embed-responsive-16by9" style="width:100%;">
                        <iframe width="560" height="315" src="about:blank" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
                <div class="is-lightbox lightbox-video light">
                    <button class="cmd-lightbox-close" title="Close" type="button" style="flex:none;position:absolute;top:0;right:0;background:none;z-index:1;">
                        <svg><use xlink:href="#icon-close"></use></svg>
                    </button>
                    <div class="lightbox-content" style="width:100%;"></div>
                </div>
                <div class="is-lightbox lightbox-image light">
                    <button class="cmd-lightbox-close" title="Close" type="button" style="flex:none;position:absolute;top:0;right:0;background:none;z-index:1;">
                        <svg><use xlink:href="#icon-close"></use></svg>
                    </button>
                    <div class="lightbox-content" style="width:100%;"></div>
                </div>
                <svg width="0" height="0" style="position:absolute;display:none;">
                    <defs>
                        <symbol viewBox="0 0 24 24" id="icon-close" stroke-width="0.7" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                        </symbol>
                    </defs>
                </svg>
            `; // Click overlay will close lightbox

         this.builderStuff.insertAdjacentHTML('afterbegin', html); // Click overlay will close lightbox

         const lightboxes = this.builderStuff.querySelectorAll('div.is-lightbox');
         lightboxes.forEach(lightbox => {
           lightbox.addEventListener('click', e => {
             if (dom.parentsHasClass(e.target, 'lightbox-content')) return;
             dom.removeClass(lightbox, 'active');
             const btnClose = lightbox.querySelector('.cmd-lightbox-close');
             btnClose.style.opacity = 0;
             document.body.style.overflowY = '';
             setTimeout(() => {
               let iframe = lightbox.querySelector('iframe');

               if (iframe) {
                 iframe.setAttribute('src', 'about:blank');
               }

               lightbox.style.display = '';
             }, 300);
           });
         }); // this.init();
       }
     } //constructor


     init() {
       // Find all elements that have .block-click & .button-click class.
       // Then read data attributes:
       //  - data-modal-theme: dark or light
       //  - data-modal-color (background color)
       //  - data-modal-image (image url)
       //  - data-modal-video (video url)
       //  - data-modal-externalvideo (ex. youtube url)
       const blockClickables = document.querySelectorAll('.block-click,.button-click,.is-lightbox');
       blockClickables.forEach(block => {
         block.addEventListener('click', e => {
           let url;
           let extension;

           if (block.tagName.toLowerCase() === 'a') {
             url = block.getAttribute('href');
             extension = url.split('.').pop();
           } else if (block.getAttribute('data-modal-url')) {
             url = block.getAttribute('data-modal-url');
             extension = url.split('.').pop();
           } else {
             if (block.getAttribute('data-modal-image') || block.getAttribute('data-modal-video') || block.getAttribute('data-modal-externalvideo')) ; else {
               // Overlay is clicked
               return;
             }
           }

           let theme = block.getAttribute('data-modal-theme');
           if (!theme) theme = 'light';
           const color = block.getAttribute('data-modal-color');

           if (extension === 'jpg' || extension === 'jpeg' || extension === 'png' || extension === 'gif' || extension === 'webm') {
             this.openImage(url, theme, color);
           } else if (extension === 'mp4') {
             this.openVideo(url, 'dark', color);
           } else if (url.toLowerCase().indexOf('youtube.com') !== -1 || url.toLowerCase().indexOf('vimeo.com') !== -1) {
             this.openExternalVideo(url, 'dark', color);
           } else {
             // Open link. But check first some attributes.
             if (block.getAttribute('data-modal-image')) {
               url = block.getAttribute('data-modal-image');
               this.openImage(url, theme, color);
             } else if (block.getAttribute('data-modal-video')) {
               url = block.getAttribute('data-modal-video');
               this.openVideo(url, 'dark', color);
             } else if (block.getAttribute('data-modal-externalvideo')) {
               url = block.getAttribute('data-modal-externalvideo');
               this.openExternalVideo(url, 'dark', color);
             } else {
               // Now open link
               window.location.href = url;
             }
           }

           e.preventDefault();
         });
       });
     } //run


     openImage(url, theme, color) {
       let lightbox = this.builderStuff.querySelector('div.is-lightbox.lightbox-image'); // in case opened in an iframe (ex. preview)

       if (window.frameElement && !lightbox) {
         lightbox = parent.document.querySelector('.is-lightbox.lightbox-image');
       }

       const btnClose = lightbox.querySelector('.cmd-lightbox-close');
       btnClose.style.opacity = 0;
       if (color) lightbox.style.backgroundColor = color;
       const div = lightbox.querySelector('.lightbox-content');
       div.innerHTML = '<img src=' + url + '>';

       if (theme === 'light') {
         dom.addClass(lightbox, 'light');
         dom.removeClass(lightbox, 'dark');
       } else {
         dom.addClass(lightbox, 'dark');
         dom.removeClass(lightbox, 'light');
       }

       lightbox.style.display = 'flex';
       if (!window.frameElement) document.body.style.overflowY = 'hidden';
       setTimeout(() => {
         dom.addClass(lightbox, 'active');
         setTimeout(() => {
           btnClose.style.opacity = 1;
         }, 450);
       }, 10);
     }

     openVideo(url, theme, color) {
       let lightbox = document.querySelector('.is-lightbox.lightbox-video'); // in case opened in an iframe (ex. preview)

       if (window.frameElement && !lightbox) {
         lightbox = parent.document.querySelector('.is-lightbox.lightbox-video');
       }

       const btnClose = lightbox.querySelector('.cmd-lightbox-close');
       btnClose.style.opacity = 0;
       if (color) lightbox.style.backgroundColor = color;
       const div = lightbox.querySelector('.lightbox-content');
       div.innerHTML = '<video class="is-video-bg" playsinline controls autoplay width="100%">' + '<source src="' + url + '" type="video/mp4">' + '</video>';

       if (theme === 'light') {
         dom.addClass(lightbox, 'light');
         dom.removeClass(lightbox, 'dark');
       } else {
         dom.addClass(lightbox, 'dark');
         dom.removeClass(lightbox, 'light');
       }

       lightbox.style.display = 'flex';
       if (!window.frameElement) document.body.style.overflowY = 'hidden';
       setTimeout(() => {
         dom.addClass(lightbox, 'active');
         setTimeout(() => {
           btnClose.style.opacity = 1;
         }, 450);
       }, 10);
     }

     openExternalVideo(url, theme, color) {
       let lightbox = document.querySelector('.is-lightbox.lightbox-externalvideo'); // in case opened in an iframe (ex. preview)

       if (window.frameElement && !lightbox) {
         lightbox = parent.document.querySelector('.is-lightbox.lightbox-externalvideo');
       }

       const btnClose = lightbox.querySelector('.cmd-lightbox-close');
       btnClose.style.opacity = 0;
       if (color) lightbox.style.backgroundColor = color;

       if (theme === 'light') {
         dom.addClass(lightbox, 'light');
         dom.removeClass(lightbox, 'dark');
       } else {
         dom.addClass(lightbox, 'dark');
         dom.removeClass(lightbox, 'light');
       }

       const iframe = lightbox.querySelector('iframe');
       url = this.getIframeVideoUrl(url);
       if (url !== '') iframe.setAttribute('src', url);
       lightbox.style.display = 'flex';
       if (!window.frameElement) document.body.style.overflowY = 'hidden';
       const div = lightbox.querySelector('.lightbox-content');
       div.style.width = div.offsetHeight * 16 / 9 + 'px';
       setTimeout(() => {
         dom.addClass(lightbox, 'active');
         setTimeout(() => {
           btnClose.style.opacity = 1;
         }, 450);
       }, 10);
     }

     getIframeVideoUrl(src) {
       // https://regex101.com/library/OY96XI
       // https://stackoverflow.com/questions/3452546/how-do-i-get-the-youtube-video-id-from-a-url
       // let youRegex = /^http[s]?:\/\/(((www.youtube.com\/watch\?(feature=player_detailpage&)?)v=)|(youtu.be\/))([^#&?]*)/;
       let youRegex = /^.*(?:https?:)?(?:\/\/)?(?:[0-9A-Z-]+\.)?(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/\S*?[^\w\s-])((?!videoseries)[\w-]{11})(?=[^\w-]|$)(?![?=&+%\w.-]*(?:['"][^<>]*>|<\/a>))[?=&+%\w.-]*/;
       let vimeoRegex = /^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/)|(video\/))?([0-9]+)\/?/; // let youRegexMatches = youRegex.exec(src);

       let youRegexMatches = src.match(youRegex);
       let vimeoRegexMatches = vimeoRegex.exec(src); // if (youRegexMatches !== null || vimeoRegexMatches !== null) {

       if ((youRegexMatches !== null || vimeoRegexMatches !== null) && src.indexOf('player.vimeo.com') === -1 && src.indexOf('youtube.com/embed/') === -1) {
         if (youRegexMatches !== null) {
           // && youRegexMatches.length >= 7) {
           // let youMatch = youRegexMatches[6];
           let youMatch = youRegexMatches[1];
           src = 'https://www.youtube.com/embed/' + youMatch + '?rel=0&autoplay=1&color=white';
         }

         if (vimeoRegexMatches !== null && vimeoRegexMatches.length >= 7) {
           let vimeoMatch = vimeoRegexMatches[6];
           src = 'https://player.vimeo.com/video/' + vimeoMatch;
         }
       }

       return src;
     }

   } //Lightbox


   window.lightbox = new Lightbox();

   return Lightbox;

})();
