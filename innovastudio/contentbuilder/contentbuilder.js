import { Util, Dom } from './util.js';
import HtmlUtil from './html.js';
import UndoRedo from './undoredo.js';
import prepareSvgIcons from './svgicons.js';
import renderGridEditor from './grideditor.js';
import renderSnippetPanel from './snippetpanel.js';
import Element from './elements.js';
import RowTool from './rowtool.js';
import RowAddTool from './rowaddtool.js';
import ColumnTool from './columntool.js';
import ElementTool from './elementtool.js';
import Preferences from './preferences.js';
import ColorPicker from './colorpicker.js';
import GradientPicker from './gradientpicker.js';
import Rte from './rte.js';
import SaveImages from './saveimages.js';
import Tooltip from './tooltip.js';
import Lightbox from './lightbox.js';
import Tabs from './tabs.js';
import Dropdown from './dropdown.js';
import Draggable from './draggable.js';
// import '../node_modules/core-js/client/core.js';
// import '../node_modules/regenerator-runtime/runtime.js';
import Sortable from 'sortablejs';

const dom = new Dom();

class ContentBuilder {
    constructor(opts = {}){

        let defaults = {
            page: '',
            container: '.container',
            row: '',
            cols: [],
            colequal: [],
            colsizes: [],
            imageQuality: 0.92,
            elementSelection : true,
            paste: 'text',
            snippetJSON: {
                'snippets': []
            },

            scriptPath: '', // Deprecated
            // Old way:
            //      For the location of config.js. Used as pluginPath (if pluginPath empty).
            //      scriptPath = '' means the path will be autoset/autodetected (see currentScriptPath())
            //      This is not reliable and so will be deprecated.

            plugins: [],
            // pluginPath: '', 
            pluginPath: 'contentbuilder/', 
            // Old way:
            //      If pluginPath is not empty, pluginPath will be used instead of scriptPath
            //      But scriptPath is still needed for the location of config.js
            // Now the new way is by specifying the plugins and pluginPath parameters. 
            // (If plugins has value [{..}], then config file won't be loaded. No need to use the scriptPath.)
            // For example:
            // plugins: [
            //     { name: 'preview', showInMainToolbar: true, showInElementToolbar: true },
            //     { name: 'wordcount', showInMainToolbar: true, showInElementToolbar: true },
            //     { name: 'symbols', showInMainToolbar: true, showInElementToolbar: false },
            //     { name: 'buttoneditor', showInMainToolbar: false, showInElementToolbar: false },
            // ],
            // pluginPath: 'contentbuilder/', // Location of the plugin scripts
            disableConfig: false, // If you don't specify the plugins param and set the disableConfig to true, then no plugins will be loaded.

            modulePath: 'assets/modules/',
            assetPath: 'assets/', // Used for the location of ionicons/ (see rte.js 2788) & scripts/ (see plugins/preview/plugin.js 237)
            fontAssetPath: 'assets/fonts/',

            snippetData: 'assets/minimalist-blocks/snippetlist.html', // Deprecated
            snippetUrl: 'assets/minimalist-blocks/content.js', //new
            // snippetUrl: 'https://innovastudio.com/builderdemo/assets/minimalist-blocks/content.js',

            // snippetPath: '',  // Old
            // if snippetPath is specified (not empty), then use the specified. 
            // Otherwise, use the one generated from snippet file (_snippets_path)
            snippetPath: 'assets/minimalist-blocks/', // New: now snippetPath is specified
            snippetPathReplace: [],// ['',''],

            // Another Example:
            //      snippetPath: 'https://innovastudio.com/builderdemo/assets/minimalist-blocks/',
            //      snippetPathReplace: ['assets/','https://innovastudio.com/builderdemo/assets/'],

            // snippetSampleImage: 'uploads/office2.png', // if enabled, will be used to insert image block
            // snippetSampleVideo: 'uploads/person1.mp4', // if enabled, will be used to insert video block

            snippetCategories: [
                [120,'Basic'],
                [118,'Article'],
                [101,'Headline'],
                [119,'Buttons'],
                [102,'Photos'],
                [103,'Profile'],
                [116,'Contact'],
                [104,'Products'],
                [105,'Features'],
                [106,'Process'],
                [107,'Pricing'],
                [108,'Skills'],
                [109,'Achievements'],
                [110,'Quotes'],
                [111,'Partners'],
                [112,'As Featured On'],
                [113,'Page Not Found'],
                [114,'Coming Soon'],
                [115,'Help, FAQ']
            ],
            defaultSnippetCategory: 120,
            snippetHandle: true,
            sidePanel: 'right',
            snippetList: '#divSnippetList',
            onRender: function(){},
            // onContentClick: function(){},
            onChange: function(){},
            // onAdd: function(){},
            largerImageHandler: '',
            // onLargerImageUpload: function(){},

            mediaHandler: '',
            // onMediaUpload: function(){},

            videoHandler: '',
            // onVideoUpload: function(){},
            colors: ['#ff8f00', '#ef6c00', '#d84315', '#c62828', '#58362f', '#37474f', '#353535',
                '#f9a825', '#9e9d24', '#558b2f', '#ad1457', '#6a1b9a', '#4527a0', '#616161',
                '#00b8c9', '#009666', '#2e7d32', '#0277bd', '#1565c0', '#283593', '#9e9e9e'],
            builderMode: '',
            rowTool: 'right',
            rowcolOutline: true,
            columnTool: true,
            outlineMode: '',
            toolStyle: '',
            outlineStyle: '',
            snippetAddTool: true,
            elementTool: true,
            elementHighlight: true,
            columnHtmlEditor: true,
            rowHtmlEditor: true,
            htmlSyntaxHighlighting: true,
            snippetOpen: false,
            toolbar: 'top',
            toolbarDisplay: 'auto',

            snippetsSidebarDisplay: 'auto', 
            // snippetDisplay: 'auto', // values: auto, visible (a new alternative). If used, will set the snippetsSidebarDisplay

            // onImageSelectClick: function () { }, 
            // onFileSelectClick: function () { },
            // onVideoSelectClick: function () { },
            // onPluginsLoaded: function () { },
            // onImageBrowseClick: function () { },
            // onImageSettingClick: function () { },
            imageEmbed: true,
            imageselect: '',
            fileselect: '',
            imageSelect: '',
            fileSelect: '',
            videoSelect: '',
            assetRefresh: true, // asset manager specified in imageSelect, fileSelect & videoSelect will always refreshed on click/open
            customTags: [],
            buttons: ['bold', 'italic', 'underline', 'formatting', 'color', 'align', 'textsettings', 'createLink', 'tags', '|', 'undo', 'redo', 'zoom', 'more'],  
            buttonsMore: ['icon', 'image', '|', 'list', 'font', 'formatPara', '|', 'html', 'preferences'], 
            elementButtons: ['left', 'center', 'right', 'full' , 'undo', 'redo', 'zoom', 'more'],  
            elementButtonsMore: ['|', 'html', 'preferences'], 
            iconButtons: ['icon', 'color','textsettings', 'createLink','|', 'undo', 'redo', 'zoom', 'more'],  
            iconButtonsMore: ['|', 'html', 'preferences'], 
            lang: [],
            checkLang: false,
            clearPreferences: false,
            toolbarAddSnippetButton: false,    
            animateModal: true,
            defaultFontSizes: [16, 17, 18, 19, 24, 32, 48, 76, 96, 120, 200, 300],
            fontSizeClassValues: [12, 14, 15, 16, 17, 18, 19, 21, 24, 28, 32, 35, 38, 42, 46, 48, 50, 54, 60, 64, 68, 72, 76, 80, 84, 88, 92, 96, 
                100, 104, 108, 112, 116, 120, 124, 128, 132, 136, 140, 144, 148, 152, 156, 160, 164, 168, 172, 176, 180, 184, 188, 192, 196, 
                200, 204, 208, 212, 216, 220, 224, 228, 232, 236, 240, 244, 248, 252, 256, 260, 264, 268, 272, 276, 280, 284, 288,
                292, 296, 300, 304, 308, 312, 316, 320, 324, 328, 332, 336, 340, 344, 348, 352, 356, 360, 364, 368, 372, 376, 380,
                384, 388, 392, 396, 400], /* If not empty, applying font size will apply class: size-12, size-14, and so on. All responsive, defined in content.css */
            gradientcolors: [
                ['linear-gradient(0deg, rgb(255, 57, 25), rgb(249, 168, 37))'],
                ['linear-gradient(0deg, rgb(255, 57, 25), rgb(255, 104, 15))'],
                ['linear-gradient(0deg, #FF5722, #FF9800)'],
                ['linear-gradient(0deg, #613ca2, rgb(110, 123, 217))'],
                ['linear-gradient(0deg, rgb(65, 70, 206), rgb(236, 78, 130))'],
                ['linear-gradient(0deg, rgb(0, 150, 102), rgb(90, 103, 197))'],
                ['linear-gradient(30deg, rgb(249, 119, 148), rgb(98, 58, 162))'],
                ['linear-gradient(0deg, rgb(223, 70, 137), rgb(90, 103, 197))'],
                ['linear-gradient(0deg, rgb(40, 53, 147), rgb(90, 103, 197))'],
                ['linear-gradient(0deg, rgb(21, 101, 192), rgb(52, 169, 239))'],
                ['linear-gradient(0deg, rgb(32, 149, 219), rgb(139, 109, 230))'],
                ['linear-gradient(0deg, rgb(90, 103, 197), rgb(0, 184, 201))'],
                ['linear-gradient(0deg, rgb(0, 184, 201), rgb(253, 187, 45))'],
                ['linear-gradient(0deg, rgb(255, 208, 100), rgb(239, 98, 159))'],
                ['linear-gradient(0deg, rgb(0, 214, 223), rgb(130, 162, 253))'],
                ['linear-gradient(0deg, rgb(50, 234, 251), rgb(248, 247, 126))'],
                ['linear-gradient(0deg, rgb(141, 221, 255), rgb(255, 227, 255))'],
                ['linear-gradient(0deg, rgb(255, 170, 170), rgb(255, 255, 200))'],
                ['linear-gradient(0deg, rgb(239, 239, 239), rgb(252, 252, 252))']
            ], 
            elementEditor: true, 
            customval: '',
            moduleConfig: [],  
            elementAnimate: false, 
            framework: '',
            cellFormat: '',
            rowFormat: '',
            // Email mode is an alternatif. 
            // ContentBuilder is focusing more on web building.
            emailMode: false,
            absolutePath: false,  
            emailSnippetCategories: [
                [1,'Logo'],
                [14,'Call to Action'],
                [2,'Title'],
                [3,'Title, Subtitle'],
                [4,'Info, Title'],
                [7,'Paragraph'],
                [6,'Heading'],
                [8,'Buttons'],
                [9,'Callouts'],
                [10,'Images + Caption'],
                [12,'Images'],
                [13,'List'],
                [15,'Pricing'],
                [16,'Quotes'],
                [17,'Profile'],
                [18,'Contact Info'],
                [19,'Footer'],
                [20,'Separator']
            ],
            defaultEmailSnippetCategory: 14,
            undoRedoStyles: false,
            // specialElementClasses: ['sl-wrapper', 'sl-overlay'] // specify elements that when clicked will not affect the builder interface (active selection). Usefull for external code, ex lightbox, etc. 
            // onUndo: function () { },
            // onRedo: function () { }

            /*
            Deprecated:
            snippetSampleImage: '',
            classReplace: [],
            iconselect: 'assets/ionicons/icons.html',
            rowMoveButtons: true,
            addButtonPlacement: '',
            sourceEditor: true,
            snippetPageSliding
            animatedSorting
            dragWithoutHandle
            */
           
            autoResizeImageEmbed: true,
            maxEmbedImageWidth: 1600, //set -1 for no max (use original image width)
            zoom: 1,
            useLightbox: false,
            imageRenameOnEdit: true,
            disableAutoEmbedVideo: false,

            /*
            onZoomStart: () => {
    
            },
            onZoom: () => {
                console.log(scale);
            },
            onZoomEnd: (scale) => {
                console.log('end: ' + scale);
            }

            themes: [
                ['#ffffff','',''],
                ['#282828','dark','contentbuilder/themes/dark.css'],
                ['#0093dc','colored','contentbuilder/themes/colored-blue.css'],
                ['#006add','colored','contentbuilder/themes/colored-blue6.css'],
                ['#0a4d92','colored','contentbuilder/themes/colored-darkblue.css'],
                ['#96af16','colored','contentbuilder/themes/colored-green.css'],
                ['#e65800','colored','contentbuilder/themes/colored-orange.css'],

                ['#cc016f','colored','contentbuilder/themes/colored-magenta.css'],
                ['#782ec5','colored','contentbuilder/themes/colored-purple.css'],
                ['#de4ea4','colored','contentbuilder/themes/colored-pink.css'],
                ['#dd3905','colored','contentbuilder/themes/colored-red.css'],
                ['#e8ae00','colored','contentbuilder/themes/colored-yellow.css'],
                ['#13b34b','colored','contentbuilder/themes/colored-green4.css'],
                ['#333333','colored-dark','contentbuilder/themes/colored-dark.css'], 
                
                ['#dbe5f5','light','contentbuilder/themes/light-blue.css'],
                ['#fbe6f2','light','contentbuilder/themes/light-pink.css'],
                ['#dcdaf3','light','contentbuilder/themes/light-purple.css'],
                ['#ffe9e0','light','contentbuilder/themes/light-red.css'],
                ['#fffae5','light','contentbuilder/themes/light-yellow.css'],
                ['#ddf3dc','light','contentbuilder/themes/light-green.css'],
                ['#d7f7f1','light','contentbuilder/themes/light-cyan.css'],

                ['#c7ebfd','light','contentbuilder/themes/light-blue2.css'],
                ['#ffd5f2','light','contentbuilder/themes/light-pink2.css'],
                ['#eadafb','light','contentbuilder/themes/light-purple2.css'],
                ['#c5d4ff','light','contentbuilder/themes/light-blue3.css'],
                ['#ffefb1','light','contentbuilder/themes/light-yellow2.css'],
                ['#e5e5e5','light','contentbuilder/themes/light-gray2.css'],
                ['#dadada','light','contentbuilder/themes/light-gray.css'],

                ['#3f4ec9','colored','contentbuilder/themes/colored-blue2.css'],
                ['#6779d9','colored','contentbuilder/themes/colored-blue4.css'],
                ['#10b9d7','colored','contentbuilder/themes/colored-blue3.css'], 
                ['#006add','colored','contentbuilder/themes/colored-blue5.css'],
                ['#e92f94','colored','contentbuilder/themes/colored-pink3.css'],
                ['#a761d9','colored','contentbuilder/themes/colored-purple2.css'],
                ['#ff9d1e','colored','contentbuilder/themes/colored-yellow2.css'],

                ['#f3522b','colored','contentbuilder/themes/colored-red3.css'],
                ['#36b741','colored','contentbuilder/themes/colored-green2.css'],
                ['#00c17c','colored','contentbuilder/themes/colored-green3.css'],
                ['#fb3279','colored','contentbuilder/themes/colored-pink2.css'],
                ['#ff6d13','colored','contentbuilder/themes/colored-orange2.css'], 
                ['#f13535','colored','contentbuilder/themes/colored-red2.css'],
                ['#646464','colored','contentbuilder/themes/colored-gray.css'],

                ['#3f4ec9','dark','contentbuilder/themes/dark-blue.css'],
                ['#0b4d92','dark','contentbuilder/themes/dark-blue2.css'],
                ['#5f3ebf','dark','contentbuilder/themes/dark-purple.css'],
                ['#e92f69','dark','contentbuilder/themes/dark-pink.css'],
                ['#006add','dark','contentbuilder/themes/dark-blue3.css'],
                ['#4c4c4c','dark','contentbuilder/themes/dark-gray.css'],
                ['#ed2828','dark','contentbuilder/themes/dark-red.css'],
            ],
            */

            colHeight: [300, 350, 400, 450, 500, 550, 600, 650, 700],
            // maxColumns: 6,

        };

        // obj.preserveSelection = true; (can be set programmatically) to prevent click that clears selection on external custom modal.

        this.opts = Object.assign(this, defaults, opts);

        if(window.data_basic) { // if snippet file included
            this.opts.snippetJSON = window.data_basic;
            // if snippetPath is specified (not empty), then use the specified. Otherwise, use the one generated from snippet file (_snippets_path)
            if(this.opts.snippetPath === '') { 
                this.opts.snippetPath = window._snippets_path;
            }
        }

        if(this.opts.snippetDisplay) {
            if(this.opts.snippetDisplay==='auto') {
                this.opts.snippetsSidebarDisplay = 'auto';

            } else { // can be any: 'stay', 'visible'
                this.opts.snippetsSidebarDisplay = 'always';
            }
        }

        // if scriptPath is specified (not empty), then use the specified. Otherwise, use generated.
        if(this.opts.scriptPath === '') { 
            this.opts.scriptPath = this.currentScriptPath();
        }

        if(window._txt) { // if language file is included
            this.opts.lang = window._txt;
        }
        
        this.settings = this.opts; // Backward compatible

        if(this.opts.imageSelect!=='') {
            this.opts.imageselect = this.opts.imageSelect;
        } else if(this.opts.imageselect!=='') {
            this.opts.imageSelect = this.opts.imageselect;
        }
        if(this.opts.fileSelect!=='') {
            this.opts.fileselect = this.opts.fileSelect;
        } else if(this.opts.fileselect!=='') {
            this.opts.fileSelect = this.opts.fileselect;
        }
        if(this.opts.videoSelect!=='') {
            this.opts.videoselect = this.opts.videoSelect;
        } else if(this.opts.videoselect!=='') {
            this.opts.videoSelect = this.opts.videoselect;
        }
        if(this.opts.largerImageHandler!=='') {
            this.opts.mediaHandler = this.opts.largerImageHandler;
        } else if(this.opts.mediaHandler!=='') {
            this.opts.largerImageHandler = this.opts.mediaHandler;
        }
        if(this.opts.onLargerImageUpload) {
            this.opts.onMediaUpload = this.opts.onLargerImageUpload;
        } else if(this.opts.onMediaUpload) {
            this.opts.onLargerImageUpload = this.opts.onMediaUpload;
        }

        // Alternative settions to define css grid frameworks
        if(this.opts.framework==='bootstrap'){
            this.opts.row = 'row';
            this.opts.cols = ['col-md-1', 'col-md-2', 'col-md-3', 'col-md-4', 'col-md-5', 'col-md-6', 'col-md-7', 'col-md-8', 'col-md-9', 'col-md-10', 'col-md-11', 'col-md-12'];
            this.opts.colequal = [];
            this.opts.colsizes = [];
        } else if(this.opts.framework==='foundation'){
            this.opts.row = 'row';
            this.opts.cols = ['large-1 columns', 'large-2 columns', 'large-3 columns', 'large-4 columns', 'large-5 columns', 'large-6 columns', 'large-7 columns', 'large-8 columns', 'large-9 columns', 'large-10 columns', 'large-11 columns', 'large-12 columns'];
            this.opts.colequal = [];
            this.opts.colsizes = [];
        } else if(this.opts.framework==='material'){
            this.opts.row = 'mdl-grid';
            this.opts.cols = ['mdl-cell mdl-cell--1-col', 'mdl-cell mdl-cell--2-col', 'mdl-cell mdl-cell--3-col', 'mdl-cell mdl-cell--4-col', 'mdl-cell mdl-cell--5-col', 'mdl-cell mdl-cell--6-col', 'mdl-cell mdl-cell--7-col', 'mdl-cell mdl-cell--8-col', 'mdl-cell mdl-cell--9-col', 'mdl-cell mdl-cell--10-col', 'mdl-cell mdl-cell--11-col', 'mdl-cell mdl-cell--12-col'];
            this.opts.colequal = [];
            this.opts.colsizes = [];
        } else if(this.opts.framework==='uikit'){
            this.opts.row = '';
            this.opts.cols = [];
            this.opts.colequal = [];
            this.opts.colsizes = [];
            this.opts.cellFormat = '<div class="uk-width-1-1"></div>';
            this.opts.rowFormat = '<div class="uk-grid"></div>';
        } else {

            // If framework param is not used
            if(this.opts.row !=='' && this.opts.cols.length>0){

                // Normal settings to define css grid frameworks => Use specified row & cols,
                // and optionally (if not 12 colus grid): colequel & colsizes.

            } else {

                if(this.opts.cellFormat==='' && this.opts.rowFormat==='') {
                    
                    // DEFAULT: Built-in simple css grid
                    this.opts.row = 'row clearfix';
                    this.opts.cols = ['column sixth', 'column fifth', 'column fourth', 'column third', 'column half', 'column two-third', 'column two-fourth', 'column two-fifth', 'column two-sixth', 'column full'];
                    this.opts.colequal = [
                        ['column sixth', 'column sixth', 'column sixth', 'column sixth', 'column sixth', 'column sixth'],
                        ['column fifth', 'column fifth', 'column fifth', 'column fifth', 'column fifth'],
                        ['column fourth', 'column fourth', 'column fourth', 'column fourth'],
                        ['column third', 'column third', 'column third'],
                        ['column half', 'column half']
                    ];
                    this.opts.colsizes = [ //needed for columns in which the size increment is not constant.
                        [   //increment for 3 columns
                            ['column third', 'column third', 'column third'],
                            ['column half', 'column fourth', 'column fourth']
                        ],
                        [   //increment for 2 columns
                            ['column sixth', 'column two-sixth'],
                            ['column fifth', 'column two-fifth'],
                            ['column fourth', 'column two-fourth'],
                            ['column third', 'column two-third'],
                            ['column half', 'column half'],
                            ['column two-third', 'column third'],
                            ['column two-fourth', 'column fourth'],
                            ['column two-fifth', 'column fifth'],
                            ['column two-sixth', 'column sixth']
                        ]
                    ];

                } else {

                    // OTHERS: use cellFormat & rowFormat. ex. Foundation Email.
                    // this.opts.cellFormat;
                    // this.opts.rowFormat;

                }
            }
        }

        this.sortableObjects = [];

        const util = new Util(this); // General utilities
        this.util = util;

        this.isTouchSupport = util.isTouchSupport();
        this.isIE = util.detectIE();
        
        if(this.opts.clearPreferences) {
            util.clearPreferences();
        }

        this.uoTm = null;
        this.uo = new UndoRedo(this);
        this.dom = dom;
        this.cbDom = dom;

        this.autoclean = false;

        this.filesAdded = '';

        if(this.opts.emailMode) {
            this.applyStyle('emailmode', '.is-builder > div {display: block}');
        }

        let builderStuff = document.querySelector('#_cbhtml'); // All editing controls will be placed within <div id="_cbhtml">...</div>
        if(!builderStuff) {
            builderStuff = dom.createElement('div');
            builderStuff.id = '_cbhtml';
            builderStuff.className = 'is-ui';
            dom.appendChild(document.body, builderStuff);
        } 
        this.builderStuff = builderStuff;

        prepareSvgIcons(); // Prepare icons (embed svg definitions for icons) 

        this.preferences = new Preferences(this); // this will also call setUIColor() and getUIStyles() for theme

        renderGridEditor(this); // Render Grid Editor

        // Check if preview

        // Get all builder areas
        this.preview = false;
        const builders = document.querySelectorAll(this.opts.container);
        Array.prototype.forEach.call(builders, (builder) => {

            if(dom.hasClass(builder, 'preview')) this.preview = true;

        });

        if(!this.preview) {
            if(this.opts.snippetJSON.snippets.length>0){
                renderSnippetPanel(this); // Render Snippet Panel
            }
        }

        this.colTool = new ColumnTool(this); // Render Column Tool

        // Extend the onChange function
        var oldget = this.opts.onChange; 
        this.opts.onChange = ()=>{

            var ret = oldget.apply(this, arguments);

            if(this.activeCol) {
                // this.colTool.refreshColumnTool(this.activeCol);
                // Quick reposition column tool
                this.util.repositionColumnTool();
            }

            this.elmTool.repositionElementTool(true);

            this.element.image.imageTool.style.display='';
            this.element.module.moduleTool.style.display='';
            this.colTool.lockIndicator.style.display='';

            return ret;
        };

        this.elmTool = new ElementTool(this); // Render Element Tool

        // Render controls or behavior for handling element editing
        this.element = new Element(this);

        this.rte = new Rte(this);

        this.tooltip = new Tooltip(this);

        this.lightbox = new Lightbox(this);

        if(!this.preview) this.applyBehavior(); // Apply editing behavior on content (builder areas)

        // Load plugins
        if(!this.preview) {
            if(this.opts.plugins.length>0) { 
                
                // Option to load plugins without config.js => just use plugins param (and specify the pluginPath).
                
                this.loadPlugins();
    
            } else {
    
                if(typeof this.opts.scriptPath==='undefined' && this.opts.pluginPath==='') {
    
                    //Do Nothing
        
                } else {
                    
                    // Backward compatibility
    
                    if(!this.opts.disableConfig) {
    
                        let scriptUrl = this.opts.scriptPath + 'config.js';
                        if(this.opts.pluginPath!=='') scriptUrl = this.opts.pluginPath + 'config.js'; // overide plugin location if pluginPath is specified
                        this.loadScript(scriptUrl).then(() => {
        
                            if(this.opts.plugins.length>0) {
                                this.loadPlugins();
                            }
        
                        }, () => {
            
                            console.log('Fail to load config');
            
                        });
    
                    }
                    
                }
    
            }
        }

        // Tooltip (move to after plugins loaded)
        // new Tooltip();

        // Run Tabs
        new Tabs();

        // Color Picker
        this.colorPicker = new ColorPicker({
            lang: this.opts.lang,
            colors: this.opts.colors
        });

        // Add document Click event
        document.addEventListener('click', this.doDocumentClick = (e)=>{
            e = e || window.event;
            var target = e.target || e.srcElement;  

            let rowClicked = dom.hasClass(target.parentNode, 'is-builder');
            let containerClicked = dom.hasClass(target, 'is-builder');
            
            let a = false, b = false, c = false, d = false, f = false, g = false, h = false, i = false, j = false, k = false;
            let m = false, n = false, o = false;
            let isSpecialElement = false;
            let element = target;
            while (element) {
                if(!element.tagName) break;
                if(element.tagName === 'BODY' || element.tagName === 'HTML') break;

                if(dom.hasClass(element, 'is-builder')) a = true;
                if(dom.hasClass(element, 'is-modal')) b = true;
                if(dom.hasClass(element, 'is-side')) c = true;
                if(dom.hasClass(element, 'is-pop')) d = true;
                if(dom.hasClass(element, 'is-tool')) f = true;
                if(dom.hasClass(element, 'is-rte-tool')||dom.hasClass(element, 'is-elementrte-tool')) g = true;
                if(dom.hasClass(element, 'is-rte-pop')) h = true;
                if(dom.hasClass(element, 'row-add-initial')) i = true;
                if(dom.hasClass(element, 'sl-wrapper') || dom.hasClass(element, 'sl-overlay') || dom.hasClass(element, 'sl-close')) j = true; 
                if(dom.hasClass(element, 'is-selectbox')||dom.hasClass(element, 'is-selectbox-options')) k = true; // dropdown

                if(this.opts.specialElementClasses) {
                    for (let i = 0; i < this.opts.specialElementClasses.length; i++) {
                        if(dom.hasClass(element, this.opts.specialElementClasses[i])) isSpecialElement = true;
                    }
                }

                if (element.id==='divImageTool') m = true;
                if (element.id==='divImageResizer') n = true;

                if(this.preserveSelection) o = true;
                
                element = element.parentNode;
            }

            // dropdown
            if(!k) { 
                let dropdowns = document.querySelectorAll('.is-selectbox-options');
                Array.prototype.forEach.call(dropdowns, (dropdown) => {
                    dropdown.style.display = 'none';
                }); 
            }

            // let a = dom.parentsHasClass(target, 'is-builder'); // builder area
            // let b = dom.parentsHasClass(target, 'is-modal'); // modal
            // let c = dom.parentsHasClass(target, 'is-side'); // side panel
            // let d = dom.parentsHasClass(target, 'is-pop'); // pop
            // let f = dom.parentsHasClass(target, 'is-tool'); // tool
            // let g = dom.parentsHasClass(target, 'is-rte-tool'); 
            // let h = dom.parentsHasClass(target, 'is-rte-pop'); 
            // let i = dom.parentsHasClass(target, 'row-add-initial'); 
            // let j = dom.hasClass(target, 'sl-overlay') || 
            //     dom.parentsHasClass(target, 'sl-overlay') || 
            //     dom.hasClass(target, 'sl-close') || 
            //     dom.parentsHasClass(target, 'sl-close'); 

            // Image Resizer
            let resizeProcess = false;
            let imageResizer = document.querySelector('#divImageResizer');
            if(imageResizer) if(imageResizer.getAttribute('data-resized')==='1') resizeProcess = true;
            if(resizeProcess) return; // to prevent unwanted click/clearActiveCell during image resize end.

            if(!(b||j||m||n||target.tagName.toLowerCase() === 'img')) {
       
                let imageTool = document.querySelector('#divImageTool');
                imageTool.style.display = '';
                this.activeImage =  null;
    
                let imageResizer = document.querySelector('#divImageResizer');
                imageResizer.style.display = 'none';
                
                // moveable
                imageResizer.style.top = '-10px';
                imageResizer.style.left = '-10px';
                imageResizer.style.width = '1px';
                imageResizer.style.height = '1px';
                this.moveable.updateRect();
                document.querySelector('.moveable-control-box').style.display = 'none'; 
            }

            if (!(a||b||c||d||f||g||h||i||j||o||isSpecialElement) || (rowClicked && !i)  || containerClicked) {
                
                // Click anywhere but is not inside builder area, modal, popup, tool or rte, then clear row/column (cell) selection
                
                if(!dom.getSelected()) {

                    util.clearActiveCell();
                
                    util.clearControls();
                }
            }

            // Backward compatibility (for examples)
            if(!d && !dom.parentsHasId(target, '_cbhtml')) {
                const pops = document.querySelectorAll('.is-pop');
                Array.prototype.forEach.call(pops, (pop) => {
                    if(!dom.parentsHasId(pop, '_cbhtml')) pop.style.display = '';
                });
            }

        });

        // Drag row will hide content tools
        document.addEventListener('mousedown', this.doDocumentMousedown = (e)=>{
            e = e || window.event;
            var target = e.target || e.srcElement;  

            let a = dom.parentsHasClass(target, 'row-handle');
            if (a) {

                util.clearControls();
                
            }
        });  

        // Undo Redo
        document.addEventListener('keydown', this.doDocumentKeydown = (e)=>{
            if (e.which === 90 && (e.ctrlKey || e.metaKey)) {//CTRL-Z
                if(e.shiftKey) this.uo.doRedo();
                else {
                    if (!e.altKey) {
                        //if(!document.queryCommandEnabled('undo')){
                        this.uo.doUndo();
                        //}
                    }
                }
            }
            if (e.which === 89 && e.ctrlKey) {//CTRL-Y
                if (!e.altKey) this.uo.doRedo();
            } 
        });
        // this.uo.saveForUndo(); //First time


        // https://stackoverflow.com/questions/4860936/how-to-remove-the-resizig-handlers-around-an-image-in-ie-with-javascript
        document.body.addEventListener('mscontrolselect', function(e){
            e.preventDefault();
        });

        // Convenience variable for outside access, for example, from snippet dialog (assets/minimalist-blocks/snippets.html))
        window._cb = this;
        window.applyLargerImage = this.applyLargerImage;
        window.returnUrl = this.returnUrl; // same as applyLargerImage (NEW)
        window.selectFile = this.selectFile.bind(this);
        window.selectImage = this.selectImage.bind(this); 
        window.selectVideo = this.selectVideo.bind(this); 
        window.selectAsset = this.selectAsset.bind(this); 
        window.imageLoaded = this.imageLoaded.bind(this); //this.imageLoaded;
        window.assetType = this.assetType.bind(this); 
    } // constructor

    // Convenience constructor, so that the plugin can be called directly using: ContentBuilder.run({ ... });
    static run(opts = {}) {
        return new ContentBuilder(opts);
    }

    setZoom() { // Complete (On Area + On Control)

        if(this.opts.page!=='') {
            const wrapper = document.querySelector(this.opts.page);
            wrapper.style.transform = `scale(${this.opts.zoom})`;

            this.setZoomOnControl(wrapper);

        } else {
            const builders = document.querySelectorAll(this.opts.container);
            builders.forEach(builder=>{

                let fade = false; // to prevent initial load animation
                if(!builder.style.transform) fade = true;
                if(fade) {
                    builder.style.transition = 'none';
                }

                builder.style.transform = `scale(${this.opts.zoom})`;

                if(fade) {
                    setTimeout(()=>{
                        builder.style.transition = '';
                    }, 300);
                }
                
                this.setZoomOnControl(builder);

            });
        }

        /*
        if(this.opts.zoom<1) {
            let ss = document.styleSheets;
            for (let i=0; i<ss.length; i++) {
                let ss = document.styleSheets;
                let rules = ss[i].cssRules || ss[i].rules;
                for (let j=0; j<rules.length; j++) {
                    if (rules[j].selectorText === '.sortable-drag') {
                        rules[j].style.background = `transparent`;
                    }
                }
            }
        }
        */
    }

    setZoomOnControl(area) {
        const rowtools = area.querySelectorAll('.is-row-tool');
        rowtools.forEach(rowtool=>{
            rowtool.style.transform = `scale(${1/this.opts.zoom})`;
            rowtool.style.transformOrigin = 'top';
        });

        const rowaddtools = area.querySelectorAll('.is-rowadd-tool button');
        rowaddtools.forEach(rowaddtool=>{
            rowaddtool.style.transform = `scale(${1/this.opts.zoom})`;
            // rowtool.style.transformOrigin = 'top';
        });
    }

    setZoomOnArea() {
        if(this.opts.page!=='') {
            const wrapper = document.querySelector(this.opts.page);
            wrapper.style.transform = `scale(${this.opts.zoom})`;
        } else {
            const builders = document.querySelectorAll(this.opts.container);
            builders.forEach(builder=>{

                let fade = false; // to prevent initial load animation
                if(!builder.style.transform) fade = true;
                if(fade) {
                    builder.style.transition = 'none';
                }

                builder.style.transform = `scale(${this.opts.zoom})`;

                if(fade) {
                    setTimeout(()=>{
                        builder.style.transition = '';
                    }, 300);
                }
            });
        }
    }

    applyBehavior() {

        // Zoom (required by the next process, eg. setZoomOnControl)
        if (localStorage.getItem('_zoom') !== null) {
            this.opts.zoom = localStorage.getItem('_zoom'); // Get from saved localStorage
        }
        this.rte.rteZoomSlider.value = this.opts.zoom*100; // Set slider value

        // Get all builder areas
        const builders = document.querySelectorAll(this.opts.container);
        Array.prototype.forEach.call(builders, (builder) => {

            this.applyBehaviorOn(builder); // includes setZoomOnControl

        });
        
        // Call onRender to indicate content is ready for editing (applyBehavior has been applied)
        this.opts.onRender();

        this.setZoomOnArea(); // Set zoom

    } // applyBehavior

    applyBehaviorOn(builder) {

        const util = this.util;

        //Make absolute
        if(this.opts.absolutePath) {
            let links = builder.querySelectorAll('a');
            Array.prototype.forEach.call(links, (link) => {
                let href = link.href;
                link.setAttribute('href', href);
            });
            let imgs = builder.querySelectorAll('img');
            Array.prototype.forEach.call(imgs, (img) => {
                let src = img.src;
                img.setAttribute('src', src);
            });
        }
    
        // Add .is-builder class on each builder area (container)
        dom.addClass(builder, 'is-builder');

        // Additional setting needed for dynamically added .is-builder. Without this, current toolStyle won't be applied to newly created section (eg. in ContentBox))
        // if (localStorage.getItem('_toolstyle') !== null) {
        //     let toolStyle = localStorage.getItem('_toolstyle');
        //     this.preferences.setToolStyle(toolStyle);
        // } 
        // if (localStorage.getItem('_outlinestyle') !== null) {
        //     let outlineStyle = localStorage.getItem('_outlinestyle');
        //     this.preferences.setOutlineStyle(outlineStyle);
        // } 
        this.preferences.initBuilder(builder);

        // Apply behavior on each row
        const rows = dom.elementChildren(builder);
        rows.forEach((row) => {

            if(dom.hasClass(row,'row-add-initial')) return;

            // Hack. If a row has margin left/right specified, don't need to set hidden border (that is used to make smooth sortable)
            if(row.style.marginLeft || row.style.marginRight) {row.style.border = 'none';}

            // On each row, add 2 tools: Row tool (div.is-row-tool) & Row Add tool (div.is-rowadd-tool)

            // Render Row tool
            this.rowtool = new RowTool(this);
            this.rowtool.render(row);

            // Render Row Add tool
            const rowaddtool = new RowAddTool(this);
            rowaddtool.render(row);

            // Apply behavior on each column
            const cols = dom.elementChildren(row);
            cols.forEach((col) => {

                if(dom.hasClass(col,'is-row-tool') || dom.hasClass(col,'is-rowadd-tool')) return; // Return if not a column
                
                // For backward compatibility, replace:
                // - data-mode="readonly" with data-noedit
                // - data-mode="readonly-protected" with data-protected
                if( col.getAttribute('data-mode') === 'readonly' ) {
                    col.setAttribute('data-noedit', '');
                    col.removeAttribute('data-mode');
                }
                if( col.getAttribute('data-mode') === 'readonly-protected' ) {
                    col.setAttribute('data-protected', '');
                    col.removeAttribute('data-mode');
                }

                // Set ContentEditable

                let noedit = false;
                if (col.hasAttribute('data-noedit')) { // Column is not (text) editable.
                    noedit = true;
                }

                let _protected = false;
                if (col.hasAttribute('data-protected')) { // Column is not (text) editable and also: cannot be deleted, moved or duplicated.
                    _protected = true;
                }

                let customcode = false;
                if (col.hasAttribute('data-html')) { // Column contains custom code.
                    customcode = true;
                }
                
                // let custommodule = false;
                // if (col.hasAttribute('data-module')) { // Column contains custom module.
                //     custommodule = true;
                // }

                if (!customcode && !noedit && !_protected) {
                    
                    // Check if column contains editable text
                    let elms = col.querySelectorAll('p,h1,h2,h3,h4,h5,h6,table,ul,ol,pre,blockquote,code,figcaption,label,legend,button,a');
                    if(elms.length>0) {

                        col.contentEditable = true; // Column is (text) editable

                    }

                }

                // Apply behavior on several elements for editing purpose
                this.element.applyBehavior(col);

                // Add events on column
                if(!col.getAttribute('data-click')) {

                    // ON CLICK
                    // Use bind() => https://www.w3schools.com/react/react_events.asp
                    col.addEventListener('click', this.handleCellClick.bind(this, col));
                    
                    // ON KEYPRESS
                    col.addEventListener('keydown', this.handleCellKeypress.bind(this));

                    // ON KEYDOWN
                    col.addEventListener('keydown', this.handleCellKeydown.bind(this, col));                        

                    // ON KEYUP
                    col.addEventListener('keyup', this.handleCellKeyup.bind(this, col));

                    // ON PASTE
                    col.addEventListener('paste', this.handleCellPaste.bind(this));

                    col.setAttribute('data-click', true);
                }

            });
        
        });

        // Sortable on each builder (container)
        if(!builder.getAttribute('data-sort') && !builder.hasAttribute('nogrid')) { // Check first if sortable has been added.

            /*
            let userAgentString = navigator.userAgent; 
            let safariAgent = userAgentString.indexOf('Safari') > -1; 
            let chromeAgent = userAgentString.indexOf('Chrome') > -1; 
            if ((chromeAgent) && (safariAgent)) safariAgent = false;
            */
            // let safariAgent = false;
            
            let sortableObject = new Sortable(builder, {
                // forceFallback: safariAgent,
                // forceFallback: true,
                scroll: true,
                // invertSwap: true, /* https://github.com/SortableJS/Sortable/wiki/Swap-Thresholds-and-Direction#swap-threshold */
                group: 'shared',
                direction: 'dummy',
                animation: 300,
                handle: '.row-handle',

                // swapThreshold: 0.1, // this cause drag drop snippet sometimes difficult
                // invertedSwapThreshold: 0.1,
                sort: true,

                onStart: () => {

                    this.uo.saveForUndo(); // Even if cancelled, saveForUndo will make sure not to save if there is no change 
                    
                    this.colTool.hideColumnTool();
                    this.elmTool.hide();
                },
                onEnd: () => {
                    if(!this.opts.emailMode) {
                        if(dom.hasClass(this.activeCol.parentNode, 'row-outline')) { //if not row selection, but col selection
                        //if(this.activeCol.parentNode.classList.contains('row-outline')) { //if not row selection, but col selection
                            this.colTool.refreshColumnTool(this.activeCol);
                            this.colTool.showColumnTool(this.activeCol);
                        }
                    }
                    
                    this.util.checkEmpty(); // for multiple instances check
                },
                onAdd: (evt) => {

                    var itemEl = evt.item;

                    if(itemEl.getAttribute('data-id')){ // If has data-id attribute, the dropped item is from snippet panel (snippetpanel.js)
                    
                        let snippetid = itemEl.getAttribute('data-id');

                        // snippetJSON is snippet's JSON (from assets/minimalist-blocks/content.js) that store all snippets' html
                        const result = this.opts.snippetJSON.snippets.filter((item) => {
                            if(item.id + ''=== snippetid) return item;
                            else return false;
                        });
                        
                        var html = result[0].html;
                        var noedit = result[0].noedit;
            
                        var bSnippet;
                        if (html.indexOf('row clearfix') === -1) {
                            bSnippet = true; // Just snippet (without row/column grid)
                        } else {
                            bSnippet = false; // Snippet is wrapped in row/colum
                        }
                        if(this.opts.emailMode) bSnippet = false;

                        // Convert snippet into your defined 12 columns grid   
                        var rowClass = this.opts.row; //row
                        var colClass = this.opts.cols; //['col s1', 'col s2', 'col s3', 'col s4', 'col s5', 'col s6', 'col s7', 'col s8', 'col s9', 'col s10', 'col s11', 'col s12']
                        if(rowClass!=='' && colClass.length===12){
                            html = html.replace(new RegExp('row clearfix', 'g'), rowClass);
                            html = html.replace(new RegExp('column full', 'g'), colClass[11]);
                            html = html.replace(new RegExp('column half', 'g'), colClass[5]);
                            html = html.replace(new RegExp('column third', 'g'), colClass[3]);
                            html = html.replace(new RegExp('column fourth', 'g'), colClass[2]);
                            html = html.replace(new RegExp('column fifth', 'g'), colClass[1]);
                            html = html.replace(new RegExp('column sixth', 'g'), colClass[1]);
                            html = html.replace(new RegExp('column two-third', 'g'), colClass[7]);
                            html = html.replace(new RegExp('column two-fourth', 'g'), colClass[8]);
                            html = html.replace(new RegExp('column two-fifth', 'g'), colClass[9]);
                            html = html.replace(new RegExp('column two-sixth', 'g'), colClass[9]);
                        }

                        html = html.replace(/{id}/g, util.makeId()); // Replace {id} with auto generated id (for custom code snippet)

                        if(this.opts.onAdd){
                            html = this.opts.onAdd(html);
                        }
            
                        if(this.opts.snippetPathReplace.length>0) {
                            // try {
                            if (this.opts.snippetPathReplace[0] !== '') {
                                var regex = new RegExp(this.opts.snippetPathReplace[0], 'g');
                                html = html.replace(regex, this.opts.snippetPathReplace[1]);
            
                                var string1 = this.opts.snippetPathReplace[0].replace(/\//g, '%2F');
                                var string2 = this.opts.snippetPathReplace[1].replace(/\//g, '%2F');
            
                                var regex2 = new RegExp(string1, 'g');
                                html = html.replace(regex2, string2);
                            }
                            // } catch (e) { 1; }
                        }

                        if (bSnippet) {
                            
                            // Just snippet (without row/column grid), ex. buttons, line, social, video, map.
                            // Can be inserted after current row, column (cell), element, or last row.
                            
                            html = `<div class="${this.opts.row}"><div class="${this.opts.cols[this.opts.cols.length-1]}"${(noedit? ' data-noedit': '')}>${html}</div></div>`;
                
                            // Clean snippet from sortable related code
                            itemEl.removeAttribute('draggable');
                            dom.removeClass(itemEl, 'snippet-item');

                            itemEl.outerHTML = html;

                        } else {

                            // Snippet is wrapped in row/colum (may contain custom code or has [data-html] attribute)
                            // Can only be inserted after current row or last row (not on column or element).
                        
                            let snippet = dom.createElement('div');
                            snippet.innerHTML = html;
                            let blocks = snippet.querySelectorAll('[data-html]');
                            Array.prototype.forEach.call(blocks, (block) => {

                                // Render custom code block
                                html = decodeURIComponent(block.getAttribute('data-html'));
                                html = html.replace(/{id}/g, util.makeId());
                                for(var i=1;i<=20;i++){
                                    html = html.replace('[%HTML'+i+'%]', (block.getAttribute('data-html-'+i) === undefined ? '' : decodeURIComponent(block.getAttribute('data-html-'+i))));//render editable area
                                }
                                block.innerHTML = html;

                            });
                            html = snippet.innerHTML;

                            // Clean snippet from sortable related code
                            itemEl.removeAttribute('draggable');
                            dom.removeClass(itemEl, 'snippet-item');
                            itemEl.innerHTML = '';

                            // Use createContextualFragment() to make embedded script executable
                            // https://ghinda.net/article/script-tags/
                            var range = document.createRange();
                            range.setStart(itemEl, 0);
                            itemEl.appendChild(
                                range.createContextualFragment(html) 
                            );

                            itemEl.outerHTML = itemEl.innerHTML;
                        }

                        // After snippet has been added, re-apply behavior on builder areas
                        this.applyBehaviorOn(builder);

                        // Call onChange
                        this.opts.onChange();
                    
                        // Hide element tool
                        this.elmTool.hide();

                    }
                }
            });

            this.sortableObjects.push(sortableObject);

            builder.setAttribute('data-sort', true);
        }
        // /Sortable



        // Additional contentEditable for subblock
        const subblocks = builder.querySelectorAll('.is-subblock');
        Array.prototype.forEach.call(subblocks, (subblock) => {
            subblock.contentEditable = true;
        });

        // Check if there is empty builder area (still has no content)
        this.util.checkEmptyOn(builder);

        // Zoom
        this.setZoomOnControl(builder);

    } 

    html(area) {

        const util = this.util;
        const htmlutil = new HtmlUtil(this); 

        if(area){
            //return area
        } else {

            const builders = document.querySelectorAll(this.opts.container);
            if(builders.length > 1) {
                const cell = util.cellSelected();
                if(!cell) {
                    // Return first instance
                    area = builders[0];
                } else {
                    // Return active instance
                    area = cell.parentNode.parentNode;
                }
            } else {
                // Single instance
                area = builders[0];
            }

            if(this.opts.page!=='') {
                const wrapper = document.querySelector(this.opts.page);
                if(wrapper) {
                    //return wrapper
                    area = wrapper;
                } 
            }

        }

        return htmlutil.readHtml(area); //for view=false

    }

    // ContentBox
    viewHtmlNormal() {
        const htmlutil = new HtmlUtil(this); 
        htmlutil.viewHtmlNormal();
    }
    viewHtmlLarger() {
        const htmlutil = new HtmlUtil(this); 
        htmlutil.viewHtmlLarger();
    }
    readHtml(content, view, multiple) { 
        const htmlutil = new HtmlUtil(this); 
        return htmlutil.readHtml(content, view, multiple);
    }
    fromViewToActual(html){
        const htmlutil = new HtmlUtil(this); 
        return htmlutil.fromViewToActual(html);
    }
    
    colorpicker(onPick, defaultcolor) {
        return new ColorPicker({
            onPick: onPick,
            color: defaultcolor,
            colors: this.opts.colors,

            animateModal: this.opts.animateModal,
            elementToAnimate: this.opts.container,
            lang: this.opts.lang                
        });
    }
    gradientpicker() {
        return new GradientPicker({
            gradientcolors: this.opts.gradientcolors,
            colors: this.opts.colors,

            animateModal: this.opts.animateModal,
            elementToAnimate: this.opts.container,
            lang: this.opts.lang   
        });
    }
    dropdown(element, opts) {
        new Dropdown(element, opts);
    }
    
    destroy() {
        
        document.removeEventListener('click', this.doDocumentClick, false);
        document.removeEventListener('keydown', this.doDocumentKeydown, false);
        document.removeEventListener('mousedown', this.doDocumentMousedown, false);

        this.util.clearControls();

        let builderStuff = this.util.builderStuff();
        if(!builderStuff) return; // in case the builder is destroyed

        const htmlutil = new HtmlUtil(this); 

        const builders = document.querySelectorAll(this.opts.container);
        Array.prototype.forEach.call(builders, (builder) => {
            builder.innerHTML = htmlutil.readHtml(builder, true);
        });

        Array.prototype.forEach.call(builders, (builder) => {
            builder.removeAttribute('data-sort');
            dom.removeClass(builder, 'is-builder');
        });

        Array.prototype.forEach.call(this.sortableObjects, (sortableObject) => {
            try{
                sortableObject.destroy();
            } catch(e) {
                // Do Nothing
            }
        });

        this.moveable.destroy();

        this.builderStuff.parentNode.removeChild(this.builderStuff);
        this.builderStuff = null;

        // Reset Zoom
        if(this.opts.page!=='') {
            const wrapper = document.querySelector(this.opts.page);
            wrapper.style.transform = '';
        } else {
            const builders = document.querySelectorAll(this.opts.container);
            builders.forEach(builder=>{
                builder.style.transform = '';
            });
        }

    }
    saveImages(handler, onComplete, onBase64Upload) {
        let si = new SaveImages({
            page: this.opts.page,
            container: this.opts.container, 
            customval: this.opts.customval,           
            handler: handler,
            onComplete: onComplete,
            onBase64Upload: (onBase64Upload?onBase64Upload:null)
        });
        si.save();
    }
    // /ContentBox

    // External (eg. from IFRAME)
    clearFont() {
        this.rte.clearFont();
    }
    applyFont(fontfamily, fontstyle, provider) { // called from iframe: parent._cb.applyFont
        this.rte.applyFont(fontfamily, fontstyle, provider);
    }
    setFont(fontfamily, fontstyle, fontdisplay, provider) { // called from iframe: parent._cb.applyFont
        this.rte.setFont(fontfamily, fontstyle, fontdisplay, provider);
    }
    addIcon(classname) {
        this.rte.addIcon(classname);
    }
    applyLargerImage(s){ // called from iframe: parent.applyLargerImage() see line 345

        let forms = document.querySelectorAll('.form-upload-larger'); // elementimage.js
        Array.prototype.forEach.call(forms, (formUpload) => {
            if(dom.hasClass(formUpload, 'please-wait')) {

                dom.removeClass(formUpload, 'please-wait');

                formUpload.parentNode.parentNode.querySelector('input[type="text"]').value = s;
                formUpload.parentNode.parentNode.querySelector('input[type="file"]').value = '';

            }

        });

    }
    returnUrl(s) { // Same as applyLargerImage (NEW)

        let forms = document.querySelectorAll('.form-upload-larger'); // elementimage.js
        Array.prototype.forEach.call(forms, (formUpload) => {
            if(dom.hasClass(formUpload, 'please-wait')) {

                dom.removeClass(formUpload, 'please-wait');

                formUpload.parentNode.parentNode.querySelector('input[type="text"]').value = s;
                formUpload.parentNode.parentNode.querySelector('input[type="file"]').value = '';

            }

        });

    }

    openImagePicker(currentUrl, callback) {

        this.colTool.openImagePicker(currentUrl, callback);
        
    }
    openImageAdjust(elm) {

        this.colTool.openImageAdjust(elm);

    }

    selectFile(s) {// Backward compatible

        this.selectAsset(s);   
    }
    selectImage(s) {// Backward compatible

        this.selectAsset(s); 
    }
    selectVideo(s) { // Backward compatible

        this.selectAsset(s); 
    }
    selectAsset(s) {

        let extension = s.split('.').pop();
        extension = extension.toLowerCase();

        let ok = false;
        if(this.targetAssetType==='all') {

            ok = true;

        } else if(this.targetAssetType==='media') {

            if(extension==='jpg'||extension==='jpeg'||extension==='png'||extension==='gif'||extension==='webm'||extension==='mp4') {
                ok = true;
            } else {
                alert(this.util.out('Please select an image or video file.'));
            }

        } else if(this.targetAssetType==='image') {

            if(extension==='jpg'||extension==='jpeg'||extension==='png'||extension==='gif'||extension==='webm') {
                ok = true;
            } else {
                alert(this.util.out('Please select an image file.'));
            }

        } else if(this.targetAssetType==='video') {

            if(extension==='mp4') {
                ok = true;
            } else {
                alert(this.util.out('Please select an mp4 file.'));
            }

        }

        if(ok) {
            this.targetInput.value = s;

            // if(this.targetCallback) this.targetCallback();
            if(this.targetInput.id==='inpCellLinkSource') { // Special case (need to run a process on asset select). See columntool.js on targetCallback.
                this.targetCallback();
            } 

            let modal = document.querySelector('.is-modal.videoselect');
            dom.removeClass(modal, 'active'); 
            modal = document.querySelector('.is-modal.imageselect');
            dom.removeClass(modal, 'active'); 
            modal = document.querySelector('.is-modal.fileselect');
            dom.removeClass(modal, 'active'); 
        }

    }
    assetType() { // not used yet
        return this.targetAssetType; // not used yet
    }
    openAsset(targetInput, targetAssetType, theTrigger) {

        if(targetAssetType==='media' && (this.opts.onFileSelectClick || this.opts.onImageSelectClick)) {

            if(this.opts.onFileSelectClick) {
                this.opts.onFileSelectClick({targetInput: targetInput, theTrigger: theTrigger});
            } else {
                this.opts.onImageSelectClick({targetInput: targetInput, theTrigger: theTrigger});
            }
            
        } else if(targetAssetType==='video' && this.opts.onVideoSelectClick) {

            this.opts.onVideoSelectClick({targetInput: targetInput, theTrigger: theTrigger});

        } else if(targetAssetType==='image' && this.opts.onImageSelectClick) {

            this.opts.onImageSelectClick({targetInput: targetInput, theTrigger: theTrigger});

        } else if(targetAssetType==='all' && this.opts.onFileSelectClick) {

            this.opts.onFileSelectClick({targetInput: targetInput, theTrigger: theTrigger});

        } else {

            let iframe;
            let modal; 
            if(targetAssetType==='media') {
                modal = this.builderStuff.querySelector('.is-modal.fileselect');
                iframe = modal.querySelector('iframe');
                if(iframe.src === 'about:blank') iframe.src = this.opts.fileSelect;
            } else if(targetAssetType==='video') {
                modal = this.builderStuff.querySelector('.is-modal.videoselect');
                iframe = modal.querySelector('iframe');
                if(iframe.src === 'about:blank') iframe.src = this.opts.videoSelect;
            } else if(targetAssetType==='image') {
                modal = this.builderStuff.querySelector('.is-modal.imageselect');
                iframe = modal.querySelector('iframe');
                if(iframe.src === 'about:blank') iframe.src = this.opts.imageSelect;
            } else if(targetAssetType==='all') {
                modal = this.builderStuff.querySelector('.is-modal.fileselect');
                iframe = modal.querySelector('iframe');
                if(iframe.src === 'about:blank') iframe.src = this.opts.fileSelect;
            }
            
            this.util.showModal(modal);

            this.targetInput = targetInput; // used by selectAsset() (see contentbuilder.js)
            this.targetCallback = null;
            this.targetAssetType = targetAssetType;
        }
    }


    LightenDarkenColor(col, amt) { // Backward compatibility (used by: Button Editor plugin)
        return this.util.LightenDarkenColor(col, amt);
    }
    pickColor(onPick, defaultcolor) { // Backward compatibility (used by: Button Editor plugin)
        let colorPicker = new ColorPicker({
            colors: this.opts.colors,
            animateModal: false,
            lang: this.opts.lang                
        });
        colorPicker.open(onPick, defaultcolor);
    }
    addSnippet(html, bSnippet, noedit) { //called by snippetlist.html
        this.util.addSnippet(html, bSnippet, noedit);
    }
    viewSnippets() {
        this.rte.viewSnippets();
    }
    saveForUndo(checkLater) {
        this.uo.saveForUndo(checkLater);
    }
    imageLoaded(obj) {
        
        obj.removeAttribute('onload');

        if(this.activeCol) {
            this.colTool.refreshColumnTool(this.activeCol);
        }
    }

    // Module related
    getActiveModule() {
        return document.querySelector('[data-module-active]');
    }
    getModuleSettings() {
        let activeModule = document.querySelector('[data-module-active]'); //get active module 
        return decodeURIComponent(activeModule.getAttribute('data-settings'));
    }
    setModuleHtml(html) {
        document.querySelector('#hidContentModuleCode').value = html; // See elementmodule.js
    }
    setModuleSettings(settings) {
        document.querySelector('#hidContentModuleSettings').value = settings; // See elementmodule.js
    }
    makeId() {
        return this.util.makeId();
    }
    setTooltip(area) {
        this.tooltip.setAll(area);
    }

    // Plugins related

    loadPlugins() {
        if(!this.builderStuff) return; // in case of builder destroyed while plugins still loading

        if(typeof this.opts.scriptPath==='undefined' && this.opts.pluginPath==='')  return;

        this.numOfPlugins = 0;
        if(typeof this.opts.plugins[0]==='string') { // backward compatible
            this.loadPluginsOldWay();
            return;
        }

        const rteTool = this.builderStuff.querySelector('.is-rte-tool');
        const rteMoreOptions = this.builderStuff.querySelector('.rte-more-options');
        const elementRteTool = this.builderStuff.querySelector('.is-elementrte-tool');
        const elementRteMoreOptions = this.builderStuff.querySelector('.elementrte-more-options');

        let i;
        for (i = this.opts.plugins.length-1; i >= 0; i--) {

            const pluginName = this.opts.plugins[i].name;
            const showInMainToolbar = this.opts.plugins[i].showInMainToolbar;
            const showInElementToolbar = this.opts.plugins[i].showInElementToolbar;

            // -- Check if button exists
            let buttonExistOnMainToolbar = false;
            if(rteTool.querySelector('[data-plugin="' + pluginName + '"]') || rteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')){ 
                buttonExistOnMainToolbar = true;
            } 

            let buttonExistOnElementToolbar = false;
            if(elementRteTool.querySelector('[data-plugin="' + pluginName + '"]') || elementRteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')){ 
                buttonExistOnElementToolbar = true;
            }
            // -- 

            if(showInMainToolbar && !buttonExistOnMainToolbar) {
                let divs = rteMoreOptions.querySelectorAll('div:not(.rte-separator)');
                divs.forEach((div)=>{
                    div.insertAdjacentHTML('afterbegin', '<button style="width:46px;" title="button not found" data-plugin="' +pluginName+ '"></button>');
                });
            }
            if(showInElementToolbar && !buttonExistOnElementToolbar) {
                elementRteMoreOptions.querySelector('div').insertAdjacentHTML('afterbegin', '<button style="width:46px;" title="button not found" data-plugin="' +pluginName+ '"></button>');
            }

        }
        for (i = 0; i < this.opts.plugins.length; i++) {

            const pluginName = this.opts.plugins[i].name;

            let pluginFile = this.opts.scriptPath + 'plugins/' + pluginName + '/plugin.js';
            if(this.opts.pluginPath!=='') pluginFile = this.opts.pluginPath + 'plugins/' + pluginName + '/plugin.js';

            this.loadScript(pluginFile).then(() => {
                this.numOfPlugins++;
                if(this.numOfPlugins===this.opts.plugins.length){
                    
                    if(this.opts.onPluginsLoaded) {
                        this.opts.onPluginsLoaded();
                    }     
                    
                    this.tooltip.setAll();
                    
                    new Draggable({selector: '.is-draggable'}); //draggable for plugins

                    // setTimeout(()=>{
                    //     this.rte.positionRteMore();
                    //     this.rte.positionElementRteMore();
                    // }, 10);
                }
            });
        }

    }

    loadPluginsOldWay() {

        const rteTool = this.builderStuff.querySelector('.is-rte-tool');
        const rteMoreOptions = this.builderStuff.querySelector('.rte-more-options');
        const elementRteTool = this.builderStuff.querySelector('.is-elementrte-tool');
        const elementRteMoreOptions = this.builderStuff.querySelector('.elementrte-more-options');

        let i;
        for (i = this.opts.plugins.length-1; i >= 0; i--) {

            const pluginName = this.opts.plugins[i];

            // -- Check if button exists
            let buttonExistOnMainToolbar = false;
            if(rteTool.querySelector('[data-plugin="' + pluginName + '"]') || rteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')){ 
                buttonExistOnMainToolbar = true;
            } 

            let buttonExistOnElementToolbar = false;
            if(elementRteTool.querySelector('[data-plugin="' + pluginName + '"]') || elementRteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')){ 
                buttonExistOnElementToolbar = true;
            }
            // -- 

            if(!buttonExistOnMainToolbar) {
                rteMoreOptions.querySelector('div').insertAdjacentHTML('afterbegin', '<button style="width:46px;" title="button not found" data-plugin="' +this.opts.plugins[i]+ '"></button>');
            }
            if(!buttonExistOnElementToolbar) {
                elementRteMoreOptions.querySelector('div').insertAdjacentHTML('afterbegin', '<button style="width:46px;" title="button not found" data-plugin="' +this.opts.plugins[i]+ '"></button>');
            }
        }
        
        for (i = 0; i < this.opts.plugins.length; i++) {

            let pluginFile = this.opts.scriptPath + 'plugins/' + this.opts.plugins[i] + '/plugin.js';
            if(this.opts.pluginPath!=='') pluginFile = this.opts.pluginPath + 'plugins/' + this.opts.plugins[i] + '/plugin.js';

            this.loadScript(pluginFile).then(() => {
                this.numOfPlugins++;
                if(this.numOfPlugins===this.opts.plugins.length){
                    
                    // All plugin scripts loaded
                    let pluginbuttons = rteMoreOptions.querySelectorAll('[data-plugin]');
                    Array.prototype.forEach.call(pluginbuttons, (pluginbutton) => {
                        pluginbutton.outerHTML = ''; //Remove unused buttons (if loaded plugins don't use button)
                    });
                    pluginbuttons = elementRteMoreOptions.querySelectorAll('[data-plugin]');
                    Array.prototype.forEach.call(pluginbuttons, (pluginbutton) => {
                        pluginbutton.outerHTML = ''; //Remove unused buttons (if loaded plugins don't use button)
                    });
                    
                    if(this.opts.onPluginsLoaded) {
                        this.opts.onPluginsLoaded();
                    }     
                    
                    this.tooltip.setAll();
                    
                    new Draggable({selector: '.is-draggable'}); //draggable for plugins

                    // setTimeout(()=>{
                    //     this.rte.positionRteMore();
                    //     this.rte.positionElementRteMore();
                    // }, 10);
                }
            });
        }
    }

    addHtml(html) {
        dom.appendHtml(this.builderStuff, html);
    }

    addCss(css) {
        dom.appendHtml(document.head, css);
    }
    
    addButton_OLD(pluginName, html, selector, exec) {
        if(!this.builderStuff) return;
        
        const rteTool = this.builderStuff.querySelector('.is-rte-tool');
        const rteMoreOptions = this.builderStuff.querySelector('.rte-more-options');
        var bUseMore = false;
        if(rteTool.querySelector('[data-plugin="' + pluginName + '"]')){ //if plugin button exists on the toolbar
            rteTool.querySelector('[data-plugin="' + pluginName + '"]').outerHTML = html;
        } else if (rteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')) { //if plugin button exists on the more popup
            rteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]').outerHTML = html;
            bUseMore = true;
        } /*else {
            rteMoreOptions.querySelector('div').insertAdjacentHTML('afterbegin', html);
            bUseMore = true;
        }*/

        // Add More button if needed
        if(!rteTool.querySelector('.rte-more') && bUseMore) {
            rteTool.insertAdjacentHTML('beforeend', `<button title="${this.util.out('More')}" class="rte-more"><svg class="is-icon-flex" style="width:13px;height:13px;"><use xlink:href="#ion-more"></use></svg></button>`);
            
            let btnRteMore = this.rteTool.querySelector('button.rte-more'); 
            if(btnRteMore) dom.addEventListener(btnRteMore, 'click', () => {
                this.rte.showRteMore();
            });
        }

        // Add event to the button
        let btnPlugin = rteTool.querySelector(selector); 
        btnPlugin = btnPlugin ? btnPlugin:rteMoreOptions.querySelector(selector);
        if(btnPlugin) {
            // Prepare for tooltip
            btnPlugin.setAttribute('data-title',btnPlugin.getAttribute('title'));
        
            dom.addEventListener(btnPlugin, 'click', (e) => {
                exec(e);
            });
        }

    }

    addButton(pluginName, html, selector, exec) {
        if(!this.builderStuff) return;
        
        const rteTool = this.builderStuff.querySelector('.is-rte-tool');
        const rteMoreOptions = this.builderStuff.querySelector('.rte-more-options');

        var bUseMore = false;
        if (rteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')) { //if plugin button exists on the more popup
            bUseMore = true;
        } 

        // Replace buttons
        const btnPlugins = this.builderStuff.querySelectorAll('[data-plugin="' + pluginName + '"]');
        btnPlugins.forEach((btn)=>{
            btn.outerHTML = html;
        });

        // Add More button if needed
        if(!rteTool.querySelector('.rte-more') && bUseMore) {
            rteTool.insertAdjacentHTML('beforeend', `<button title="${this.util.out('More')}" class="rte-more"><svg class="is-icon-flex" style="width:13px;height:13px;"><use xlink:href="#ion-more"></use></svg></button>`);
            
            let btnRteMore = this.rteTool.querySelector('button.rte-more'); 
            if(btnRteMore) dom.addEventListener(btnRteMore, 'click', () => {
                this.rte.showRteMore();
            });
        }

        let btnPlugin = rteTool.querySelectorAll(selector); 
        btnPlugin.forEach((btn)=>{
            // Prepare for tooltip
            btn.setAttribute('data-title',btn.getAttribute('title'));
                    
            dom.addEventListener(btn, 'click', (e) => {
                exec(e);
            });
        });
        btnPlugin = rteMoreOptions.querySelectorAll(selector); 
        btnPlugin.forEach((btn)=>{
            // Prepare for tooltip
            btn.setAttribute('data-title',btn.getAttribute('title'));
                    
            dom.addEventListener(btn, 'click', (e) => {
                exec(e);
            });
        });

    }

    // Element Toolbar
    addButton2(pluginName, html, selector, exec) {
        if(!this.builderStuff) return;

        const elementRteTool = this.builderStuff.querySelector('.is-elementrte-tool');
        const elementRteMoreOptions = this.builderStuff.querySelector('.elementrte-more-options');
        var bUseMore = false;
        if(elementRteTool.querySelector('[data-plugin="' + pluginName + '"]')){ //if plugin button exists on the toolbar
            elementRteTool.querySelector('[data-plugin="' + pluginName + '"]').outerHTML = html;
        } else if (elementRteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]')) { //if plugin button exists on the more popup
            elementRteMoreOptions.querySelector('[data-plugin="' + pluginName + '"]').outerHTML = html;
            bUseMore = true;
        } /*else {
            elementRteMoreOptions.querySelector('div').insertAdjacentHTML('afterbegin', html);
            bUseMore = true;
        }*/

        // Add More button if needed
        if(!elementRteTool.querySelector('.rte-more') && bUseMore) {
            elementRteTool.insertAdjacentHTML('beforeend', `<button title="${this.util.out('More')}" class="rte-more"><svg class="is-icon-flex" style="width:13px;height:13px;"><use xlink:href="#ion-more"></use></svg></button>`);
            
            let btnRteMore = this.elementRteTool.querySelector('button.rte-more'); 
            if(btnRteMore) dom.addEventListener(btnRteMore, 'click', () => {
                this.rte.showElementRteMore();
            });
        }

        // Add event to the button
        let btnPlugin = elementRteTool.querySelector(selector); 
        btnPlugin = btnPlugin ? btnPlugin:elementRteMoreOptions.querySelector(selector);
        if(btnPlugin) {
            // Prepare for tooltip
            btnPlugin.setAttribute('data-title',btnPlugin.getAttribute('title'));
        
            dom.addEventListener(btnPlugin, 'click', (e) => {
                exec(e);
            });
        }
        
    }

    showModal(modal, overlayStay, cancelCallback, animated) {
        this.util.showModal(modal, overlayStay, cancelCallback, animated);
    }

    hideModal(modal) {
        this.util.hideModal(modal);
    }

    draggable(selector) {
        new Draggable({selector: selector});
    }

    createModal(modal, selector, overlayStay, cancelCallback, animated) {
        let embeddedModal = this.builderStuff.querySelector(selector);
        if(!embeddedModal) {
            this.builderStuff.appendChild(modal);
            embeddedModal = this.builderStuff.querySelector(selector);
        } 
        this.showModal(embeddedModal, overlayStay, cancelCallback, animated); 
        new Draggable({selector: '.is-draggable'}); 
        return embeddedModal;
    }

    getIcons() {
        return this.rte.getIcons();
    }

    showSidePanel(panel) {

        let panels = this.builderStuff.querySelectorAll('.is-side');
        Array.prototype.forEach.call(panels, (panel) => {
            dom.removeClass(panel, 'active');
        });
        dom.addClass(panel, 'active');
        
    }

    hideSidePanel(panel) {
        dom.removeClass(panel, 'active');
    }

    getScriptPath() {
        return this.opts.scriptPath;
    }

    getSnippetPath() {
        // return this.opts.snippetData.substring(0, this.opts.snippetData.lastIndexOf('/') + 1);
        return this.opts.snippetPath;
    }

    out(text) {
        return this.util.out(text);
    }

    undo() {
        this.uo.doUndo();
    }

    redo() {
        this.uo.doRedo();
    }

    getScope() {

        let area;

        const builders = document.querySelectorAll(this.opts.container);
        if(builders.length > 1) {
            const cell = this.util.cellSelected();
            if(!cell) {
                // Return first instance
                area = builders[0];
            } else {
                // Return active instance
                area = cell.parentNode.parentNode;
            }
        } else {
            // Single instance
            area = builders[0];
        }

        if(this.opts.page!=='') {
            const wrapper = document.querySelector(this.opts.page);
            if(wrapper) {
                //return wrapper
                area = wrapper;
            } 
        }
        
        return area;

    }

    pasteHtmlAtCaret(html, selectPastedContent) {
        this.util.pasteHtmlAtCaret(html, selectPastedContent);
    }

    getScript(scriptUrl, callback) {

        this.loadScript(scriptUrl).then(() => {

            if(callback) callback();

        }, () => {

            console.log('Fail to load config');

        });
    }

    getScripts(scriptUrls, callback) {

        let count = 0;

        //console.log(count)
        let a = this.loadScript(scriptUrls[count]);
        a.then(() => {

            count = this._helper1(count,scriptUrls,callback);

        });


    }

    _helper1(count,scriptUrls,callback) {
        count++;
        if(count<scriptUrls.length) {

            //console.log(count)
            let a = this.loadScript(scriptUrls[count]);
            a.then(() => {

                count = this._helper1(count,scriptUrls,callback);
    
            });
            
            return count;
        } else {
            if(callback) callback();
        }
    }

    includeJs(filename, callback) {
        if (this.filesAdded.indexOf('[' + filename + ']') === -1) {
   
            this.getScript(filename, callback);
          
            this.filesAdded += '[' + filename + ']';
        } else {
            //alert('already added');
            if(callback)callback();
        }
    }

    includeCss(filename) {
        if (this.filesAdded.indexOf('[' + filename + ']') === -1) {

            var inc = document.createElement('link');
            inc.setAttribute('rel', 'stylesheet');
            inc.setAttribute('type', 'text/css');
            inc.setAttribute('href', filename);
            document.getElementsByTagName('head')[0].appendChild(inc);

            this.filesAdded += '[' + filename + ']';
        } else {
            //alert('already added');
        }
    }

    applyStyle(id, s) {
        if (this.filesAdded.indexOf('[' + id + ']') === -1) {

            var inc = document.createElement('style');
            inc.innerHTML = s;
            document.getElementsByTagName('head')[0].appendChild(inc);

            this.filesAdded += '[' + id + ']';
        } else {
            //alert('already added');
        }
    }
    // /Plugins related

    viewHtml(area) {
        const htmlutil = new HtmlUtil(this); 
        htmlutil.view('full', area);
    }

    viewPreferences() {
        this.preferences.view();
    }
    
    viewConfig() { //backward
        this.preferences.view();
    }

    viewZoom() {
        this.rte.viewZoom();
    }

    loadSnippets(snippetFile) {

        if(this.preview) return;

        let snippetPanel = document.querySelector(this.opts.snippetList);
        if(snippetPanel) return; // do not render if already rendered (just protection)
        
        if(this.isScriptAlreadyIncluded(snippetFile)) return;
        
        const script = document.createElement('script');
        script.src = snippetFile; 
        script.async = true;
        script.onload = () => {

            this.opts.snippetJSON = window.data_basic;
            // if snippetPath is specified (not empty), then use the specified. Otherwise, use the one generated from snippet file (_snippets_path)
            if(this.opts.snippetPath === '') { 
                this.opts.snippetPath = window._snippets_path;
            }
            
            if(this.opts.snippetJSON.snippets.length>0){
                renderSnippetPanel(this); // Render Snippet Panel
            }

        };
        document.body.appendChild(script);

    }

    isScriptAlreadyIncluded(src){
        const scripts = document.getElementsByTagName('script');
        for(let i = 0; i < scripts.length; i++) 
            if(scripts[i].getAttribute('src') === src) return true;
        return false;
    }
    
    loadHtml(html, area) {
        
        const util = this.util;

        if(area){
            //return area
        } else {

            const builders = document.querySelectorAll(this.opts.container);
            if(builders.length > 1) {
                const cell = util.cellSelected();
                if(!cell) {
                    // Return first instance
                    area = builders[0];
                } else {
                    // Return active instance
                    area = cell.parentNode.parentNode;
                }
            } else {
                // Single instance
                area = builders[0];
            }

            if(this.opts.page!=='') {
                const wrapper = document.querySelector(this.opts.page);
                if(wrapper) {
                    //return wrapper
                    area = wrapper;
                } 
            }

        }

        // area.innerHTML = html;
        let range = document.createRange();
        area.innerHTML = '';
        area.appendChild(range.createContextualFragment(html)); // We use createContextualFragment so that embedded javascript code (code block) will be executed

        this.applyBehavior();
        
        // this.uo.saveForUndo(); //First time

        //Trigger Change event
        this.opts.onChange();

        //Trigger Render event
        this.opts.onRender();

    }
    // loadHtml(html) {

    //     let area = this.getScope();
    //     area.innerHTML = html;

    //     this.builder.applyBehavior();
            
    //     //Trigger Change event
    //     this.builder.opts.onChange();
                
    //     //Trigger Render event
    //     this.builder.opts.onRender();
    // }
    loadHTML(html) { //backward
        this.loadHtml(html);
    }

    setUIColor(mode, csslink) {

        // Get previous css
        let cssLink = document.querySelector('[data-cb-color]');
        if(cssLink) cssLink.setAttribute('data-cb-del','');
        // if(cssLink) cssLink.parentNode.removeChild(cssLink);

        // Add new css
        if(csslink) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = csslink;
            link.setAttribute('data-cb-color','');
            link.addEventListener('load',  () => {
    
                // Remove previous css
                cssLink = document.querySelector('[data-cb-del]');
                if(cssLink) cssLink.parentNode.removeChild(cssLink);

                if(mode==='dark') {
                    dom.removeClass(document.body, 'colored');
                    dom.removeClass(document.body, 'colored-dark');
                    dom.removeClass(document.body, 'light');
                    dom.addClass(document.body, 'dark');
                }
                if(mode==='colored') {
                    dom.removeClass(document.body, 'dark');
                    dom.removeClass(document.body, 'colored-dark');
                    dom.removeClass(document.body, 'light');
                    dom.addClass(document.body, 'colored');
                }
                if(mode==='colored-dark') {
                    dom.removeClass(document.body, 'dark');
                    dom.removeClass(document.body, 'colored');
                    dom.removeClass(document.body, 'light');
                    dom.addClass(document.body, 'colored-dark');
                }
                if(mode==='') {
                    dom.removeClass(document.body, 'dark');
                    dom.removeClass(document.body, 'colored-dark');
                    dom.removeClass(document.body, 'colored');
                    dom.removeClass(document.body, 'light');
                }
                if(mode==='light') {
                    dom.removeClass(document.body, 'dark');
                    dom.removeClass(document.body, 'colored-dark');
                    dom.removeClass(document.body, 'colored');
                    dom.addClass(document.body, 'light');
                }

                // Css loaded. Now we can get UI styles.
                const util = this.util;

                // util.getUIStyles();

                // this.setUIColorRefresh();

                // if(this.opts.onThemeChange) this.opts.onThemeChange();

                setTimeout(()=>{

                    util.getUIStyles();

                    this.setUIColorRefresh();
    
                    if(this.opts.onThemeChange) this.opts.onThemeChange();
                }, 300);

            });
            document.head.appendChild(link);

        } else {

            // Remove previous css
            cssLink = document.querySelector('[data-cb-del]');
            if(cssLink) cssLink.parentNode.removeChild(cssLink);
            
            if(mode==='') {
                dom.removeClass(document.body, 'dark');
                dom.removeClass(document.body, 'colored-dark');
                dom.removeClass(document.body, 'colored');
                dom.removeClass(document.body, 'light');
            }

            // Css loaded. Now we can get UI styles.
            const util = this.util;

            // util.getUIStyles();

            // this.setUIColorRefresh();

            // if(this.opts.onThemeChange) this.opts.onThemeChange();

            setTimeout(()=>{

                util.getUIStyles();

                this.setUIColorRefresh();
    
                if(this.opts.onThemeChange) this.opts.onThemeChange();
            }, 1000);
            
        }

    }

    setUIColorRefresh() {
        
        const util = this.util;

        if(!this.rte || !this.element) { // || this.element
            setTimeout(()=>{
                // console.log('Not ready, delay 1!');
                this.setUIColorRefresh();
            }, 600);
            return;
        }
        
        // Font Family
        let iframeRte = this.rte.rteFontFamilyOptions.querySelector('iframe');
        let doc1 = iframeRte.contentWindow.document;

        const fontModal = this.builderStuff.querySelector('.is-modal.pickfontfamily');
        let iframePanel = fontModal.querySelector('iframe');
        let doc2 = iframePanel.contentWindow.document;

        // Icons
        let iframeIcons = this.rte.rteIconOptions.querySelector('iframe');
        let doc3 = iframeIcons.contentWindow.document;

        if(doc1.body.innerHTML==='') {
            doc1.open();
            if(!this.opts.emailMode) {
                doc1.write(util.getFontFamilyHTML());
            } else {
                doc1.write(util.getFontFamilyEmail());
            }
            doc1.close();
        } else {
            this.util.refreshFontFamilyStyle1();
        }

        if(doc2.body.innerHTML==='') {
            doc2.open();
            if(!this.opts.emailMode) {
                doc2.write(util.getFontFamilyHTML(true));
            } else {
                doc2.write(util.getFontFamilyEmail(true));
            }
            doc2.close();
        } else {
            this.util.refreshFontFamilyStyle2();
        }

        if(doc3.body.innerHTML==='') {
            doc3.open();
            doc3.write(this.rte.getIconsHTML());
            doc3.close();
        } else {
            this.rte.refreshIconsStyle();
        }

        // Snippets (Modal)
        let modalSnippets = this.builderStuff.querySelector('.snippets');
        modalSnippets.querySelector('iframe').src= 'about:blank';

        
        let modal1 = document.querySelector('.is-modal.videoselect');
        let modal2 = document.querySelector('.is-modal.imageselect');
        let modal3 = document.querySelector('.is-modal.fileselect');
        let modalIframe1 = modal1.querySelector('iframe');
        let modalIframe2 = modal2.querySelector('iframe');
        let modalIframe3 = modal3.querySelector('iframe');
        if(modalIframe1.contentWindow.applyParentStyles) modalIframe1.contentWindow.applyParentStyles();
        if(modalIframe2.contentWindow.applyParentStyles) modalIframe2.contentWindow.applyParentStyles();
        if(modalIframe3.contentWindow.applyParentStyles) modalIframe3.contentWindow.applyParentStyles();

    }

    openMedia(elm) {
        let url = '';
    
        let theme = elm.getAttribute('data-modal-theme');
        if(!theme) theme='light';

        const color = elm.getAttribute('data-modal-color');

        if(elm.getAttribute('data-modal-url')) {
            url = elm.getAttribute('data-modal-url');

            let extension = url.split('.').pop();

            if(extension==='jpg'||extension==='jpeg'||extension==='png'||extension==='gif'||extension==='webm') {
                this.lightbox.openImage(url, theme, color);
            } else if(extension==='mp4') {
                this.lightbox.openVideo(url, 'dark', color);
            } else if(url.toLowerCase().indexOf('youtube.com')!==-1 || url.toLowerCase().indexOf('vimeo.com')!==-1) {
                this.lightbox.openExternalVideo(url, 'dark', color);
            } else {
                if(url.indexOf('#')===0) {
                    // Do Nothing
                } else {

                    // Will go to the link
                    const answer = window.confirm(
                        this.util.out('Do you really want to leave?')
                    );
                    // cancel the navigation and stay on the same page
                    if (!answer) {
                        return false;
                    }
                }

                window.location.href = url;
            }
        } else {
            // Other options
            if(elm.getAttribute('data-modal-image')) {
                url = elm.getAttribute('data-modal-image');
                this.lightbox.openImage(url, theme, color);
            }
            if(elm.getAttribute('data-modal-video')) {
                url = elm.getAttribute('data-modal-video');
                this.lightbox.openVideo(url, 'dark', color);
            }
            if(elm.getAttribute('data-modal-externalvideo')) {
                url = elm.getAttribute('data-modal-externalvideo');
                this.lightbox.openExternalVideo(url, 'dark', color);
            }
        }
    }

    lightboxOpen(col) {
 
        // Open Lightbox
        if(this.useLightbox) {

            if((col.hasAttribute('data-noedit') || col.hasAttribute('data-protected')) && dom.hasClass(col,'block-click') /*  && dom.hasClass(col,'cell-active') */) {
        
                this.openMedia(col);

            }
        }
    }

    handleCellClick(col, e) {
        
        this.lightboxOpen(col);

        const util = this.util;
        util.clearActiveCell();

        this.activeCol = col;

        let elm = e.target;

        dom.addClass(col, 'cell-active');
        let row = col.parentNode; //Since a col can be moved to another row, then re-get the active row
        dom.addClass(row, 'row-active');

        const builders = document.querySelectorAll(this.opts.container);
        Array.prototype.forEach.call(builders, (builder) => {
            dom.removeClass(builder, 'builder-active');
        });
        dom.addClass(row.parentNode, 'builder-active');

        if(row.childElementCount - 2 === 1) { // If there is only 1 column, do not add row-outline class
            
        } else {
            dom.addClass(row, 'row-outline');
        }

        //this.getState();

        // Call onContentClick to indicate click on editable content (eg. for plugin usage)
        if (!col.hasAttribute('data-html')) {
            if(this.opts.onContentClick) this.opts.onContentClick(e);
        }

        this.element.click(col, e);
        this.colTool.click(col);
        this.elmTool.click(col, e);
        //this.rowtool.click(col);
        this.rte.click(col, e);
        // Grid Editor Status
        // On/off lock button
        const grideditor = document.querySelector('.grideditor.active');
        if(grideditor) {
            const btnCellLocking = grideditor.querySelector('.cell-locking');
            if(col.hasAttribute('data-noedit')) {
                dom.addClass(btnCellLocking, 'on');
            } else {
                dom.removeClass(btnCellLocking, 'on');
            }
        }

        let link = dom.getParentElement(elm,'a');
        if(elm.tagName.toLowerCase() === 'a' || link) {

            if((col.hasAttribute('data-noedit') || col.hasAttribute('data-protected'))) { // Link & Lightbox click allowed only on locked column
        
                // Disable link open on builder area
                if(col.getAttribute('contenteditable')==='true') {

                    e.preventDefault();
                    return false;

                } else {

                    if(elm.tagName.toLowerCase() === 'a') link = elm;
                
                    // Open Lightbox
                    if(this.useLightbox && dom.hasClass(link, 'is-lightbox')) {

                        let url = link.getAttribute('href');
                        if(!url) {
                            e.preventDefault();
                            return false;
                        }
        
                        let extension = url.split('.').pop();
        
                        let theme = link.getAttribute('data-modal-theme');
                        if(!theme) theme='light';
        
                        const color = elm.getAttribute('data-modal-color');
        
                        if(extension==='jpg'||extension==='jpeg'||extension==='png'||extension==='gif'||extension==='webm') {
                            this.lightbox.openImage(url, theme, color);
        
                            e.preventDefault();
                            return false;
                        } else if(extension==='mp4') {
                            this.lightbox.openVideo(url, 'dark', color);
        
                            e.preventDefault();
                            return false;
                        } else if(url.toLowerCase().indexOf('youtube.com')!==-1 || url.toLowerCase().indexOf('vimeo.com')!==-1) {
                            this.lightbox.openExternalVideo(url, 'dark', color);
        
                            e.preventDefault();
                            return false;
                        } else {
                            // Will go to the link
                            if(url.indexOf('#')===0) {
                                // Do Nothing
                            } else {
                                const answer = window.confirm(
                                    this.util.out('Do you really want to leave?')
                                );
                                // cancel the navigation and stay on the same page
                                if (!answer) {
                                    e.preventDefault();
                                    return false;
                                }
                            }

                        }
        
                    } else {

                        let url = link.getAttribute('href');
                        if(!url) {
                            e.preventDefault();
                            return false;
                        }

                        if(url.indexOf('#')===0) {
                            // Do Nothing
                        } else {
                            const answer = window.confirm(
                                this.util.out('Do you really want to leave?')
                            );
                            // cancel the navigation and stay on the same page
                            if (!answer) {
                                e.preventDefault();
                                return false;
                            }
                        }
        
                    }
                    
                }

            }

            // e.preventDefault();
            // return false;
        }
        
        // LATER: (builder inside builder)
        // if (jQuery(e.target).parents('.is-builder').length>1) {
        //     e.preventDefault();
        //     e.stopImmediatePropagation();
        // }

        // If column only has spacer, locked or unlocked, 
        // the text should not be able to edit (cursor should not be able to be placed)
        // So, make the column uneditable (text) if only has a spacer.
        if(col.childElementCount===1) {
            let elm = col.children[0];
            if(elm) if(dom.hasClass(elm, 'spacer')) {
                col.contentEditable = false;
            }
        }

        // Show Lock Indicator
        this.colTool.showHideLockIndicator(col);

        // Check if last row, make sure the Row Add Tool visible
        const tool = row.querySelector('.is-rowadd-tool');
        const tollAddButton = tool.querySelector('button');
        tollAddButton.style.marginTop = '';
        const children = Array.from(row.parentNode.children).filter((element) => {
            return element.nodeName.toLowerCase() === 'div';
        });
        if (children && children.length > 0) {
            let lastChild = children[children.length-1];
            if(row===lastChild) {
                const section = row.parentNode.parentNode;
                const sectionOffset = dom.getElementOffset(section);
                let h1 = sectionOffset.top + sectionOffset.height;

                const rowOffset = dom.getElementOffset(row);
                let h2 = rowOffset.top + rowOffset.height;
                if((h1-h2) < 19) {
                    tollAddButton.style.marginTop = '-14px';
                }
            }
        }
    }

    handleCellKeypress(e) {
        if(e.ctrlKey || e.metaKey) {//CTRL
            return;
        }
        if(this.uoTm===null) {
            this.uo.saveForUndo();
            
            this.uoTm = setTimeout(()=>{ this.uoTm = null; }, 1000);
        } else {
            clearTimeout(this.uoTm);
            this.uoTm = setTimeout(()=>{ this.uoTm = null; }, 1000);
        }
    }
    
    handleCellKeydown(col, e) {

        // Cleanup unwanted span
        let spans = col.querySelectorAll('span');
        Array.prototype.forEach.call(spans, (span) => {
            span.setAttribute('data-keep', '');
        });

        // // Cleanup all elements with unwanted style
        // let elms = col.querySelectorAll('*');
        // Array.prototype.forEach.call(elms, (elm) => {
        //     let attr = elm.getAttribute('style');
        //     if(attr) {
        //         if(attr.indexOf('font-size')!==-1){
        //             elm.setAttribute('data-keep-font-size','');
        //         }
        //         if(attr.indexOf('background-color')!==-1){
        //             elm.setAttribute('data-keep-background-color','');
        //         }
        //         if(attr.indexOf('background')!==-1){
        //             elm.setAttribute('data-keep-background','');
        //         }
        //         if(attr.indexOf('line-height')!==-1){
        //             elm.setAttribute('data-keep-line-height','');
        //         }
        //     }
        // });
        // this.autoclean=true;
        
        if((e.ctrlKey || e.metaKey) && e.which === 86) {//CTRL-V
            this.handleCellPaste();
        }

        if(this.opts.elementSelection && !this.emailMode) {
                            
            if((e.ctrlKey || e.metaKey) && e.which === 65) {//CTRL-A

                let el;
                try{
                    if (window.getSelection) {
                        el = window.getSelection().getRangeAt(0).commonAncestorContainer.parentNode;
                    }
                    else if (document.selection) {
                        el = document.selection.createRange().parentElement();
                    }

                    if(!dom.hasClass(el.parentNode.parentNode, 'is-builder')) {
                        dom.selectElementContents(el);
                    }
                
                    e.preventDefault();

                } catch(e) {
                    // Do Nothing
                }
            }
        }
        
        if(e.keyCode===46) { 
            // console.log("delete");
            let curr;
            try{
                if (window.getSelection) {
                    curr = window.getSelection().getRangeAt(0).commonAncestorContainer;
                }
                else if (document.selection) {
                    curr = document.selection.createRange();
                }
                if(curr.innerHTML==='<br>') { 
                    let next = curr.nextElementSibling;                         
                    if(next){
                        curr.parentNode.removeChild(curr); //without this, empty P, H1, H2 (contains only <br />) cannot be deleted (when there is another P below)
                        // Of course, we can backspace from the P below, but the formatting will change.
                        e.preventDefault();
                    }
                }

            } catch(e) {
                // Do Nothing
            }
        }

        if(e.keyCode===8) { // delete on Mac
            let curr;
            try{
                if (window.getSelection) {
                    curr = window.getSelection().getRangeAt(0).commonAncestorContainer;
                }
                else if (document.selection) {
                    curr = document.selection.createRange();
                }
                if(curr.textContent===''){
                    let prev = curr.previousElementSibling; 
                    if(!prev) {
                        e.preventDefault(); //Without this, empty P, H1, H2 (that doesn't have prev element) will be lost => can make empty column.
                    }
                }

            } catch(e) {
                // Do Nothing
            }
        }

    }

    handleCellKeyup(col, e) {
        
        if(dom.textSelection()) {
            if (e.keyCode === '38') {
                // Up arrow
                this.util.saveSelection();
            }
            else if (e.keyCode === '40') {
                // Down arrow
                this.util.saveSelection();
            }
            else if (e.keyCode === '37') {
                // Left arrow
                this.util.saveSelection();
            }
            else if (e.keyCode === '39') {
                // Right arrow
                this.util.saveSelection();
            }
        }

        // See: handleCellKeydown
        // Cleanup unwanted span
        let spans = col.querySelectorAll('span');
        Array.prototype.forEach.call(spans, (span) => {
            let attr = span.hasAttribute('data-keep');
            if(!attr) {
                // Remove unwanted span
                span.outerHTML = span.innerHTML;
            }
        });

        // // Cleanup all elements with unwanted style
        // if(this.autoclean){
        //     let elms = col.querySelectorAll('*');
        //     Array.prototype.forEach.call(elms, (elm) => {
        //         let attr = elm.getAttribute('style');
        //         if(attr) {
        //             if(attr.indexOf('font-size')!==-1){
        //                 let attrkeep = elm.getAttribute('data-keep-font-size');
        //                 if (!attrkeep) {
        //                     // Remove unwanted font size
        //                     elm.style.fontSize = '';
        //                 }
        //             }
        //             if(attr.indexOf('background-color')!==-1){
        //                 let attrkeep = elm.getAttribute('data-keep-background-color');
        //                 if (!attrkeep) {
        //                     // Remove unwanted background color
        //                     elm.style.backgroundColor = '';
        //                 }
        //             }
        //             if(attr.indexOf('background')!==-1){
        //                 let attrkeep = elm.getAttribute('data-keep-background');
        //                 if (!attrkeep) {
        //                     // Remove unwanted background
        //                     elm.style.background = '';
        //                 }
        //             }
        //             if(attr.indexOf('line-height')!==-1){
        //                 let attrkeep = elm.getAttribute('data-keep-line-height');
        //                 if (!attrkeep) {
        //                     // Remove unwanted line height
        //                     elm.style.lineHeight = '';
        //                 }
        //             }
        //         }
        //     });
        //     elms = col.querySelectorAll('[data-keep-font-size]');
        //     Array.prototype.forEach.call(elms, (elm) => {
        //         elm.removeAttribute('data-keep-font-size');
        //     });
        //     elms = col.querySelectorAll('[data-keep-background-color]');
        //     Array.prototype.forEach.call(elms, (elm) => {
        //         elm.removeAttribute('data-keep-background-color');
        //     });
        //     elms = col.querySelectorAll('[data-keep-background]');
        //     Array.prototype.forEach.call(elms, (elm) => {
        //         elm.removeAttribute('data-keep-background');
        //     });
        //     elms = col.querySelectorAll('[data-keep-line-height]');
        //     Array.prototype.forEach.call(elms, (elm) => {
        //         elm.removeAttribute('data-keep-line-height');
        //     });                       
        // }
        // this.autoclean =false;
        
        var el;
        // var curr;
        try{
            if (window.getSelection) {
                // curr = window.getSelection().getRangeAt(0).commonAncestorContainer;
                el = window.getSelection().getRangeAt(0).commonAncestorContainer.parentNode;
            }
            else if (document.selection) {
                // curr = document.selection.createRange();
                el = document.selection.createRange().parentElement();
            }
        } catch(e) {return;} //Use try to prevent lost selection after undo

        if (e.keyCode === 13 && !e.shiftKey){ 

            //So that enter at the end of list returns <p>
            /*
            var isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
            var isOpera = window.opera;
            if(isChrome || isOpera) { 
                //Without this, pressing ENTER at the end of list will returns <p> on Chrome but then it become <div> (On Opera it returns <div>)
                //With this, we change it into <p>
                if(el.tagName.toLowerCase() === 'p' || el.tagName.toLowerCase() === 'div') {
                    document.execCommand('formatBlock', false, '<p>');
                }
            }*/
            if(el.tagName.toLowerCase() === 'p' || el.tagName.toLowerCase() === 'div') {
                document.execCommand('formatBlock', false, '<p>');
            }
            
            /*
            if(isFirefox) {
                //On FF (when enter at the end of list) jQuery(curr).html() returns undefined
                if(curr) document.execCommand('formatBlock', false, '<p>');
            }*/

            
            let elms = col.querySelectorAll('[data-keep]');
            Array.prototype.forEach.call(elms, (elm) => {
                elm.removeAttribute('data-keep');
            });
        }

        // LATER
        // jQuery('.cell-active').find('.elm-active').removeClass('elm-active');
        // jQuery("#divElementTool").css("display", "none"); //force hide ellement tool

        // Call onChange to indicate content change
        this.opts.onChange();

        // Hide element highlight & tool during typing
        this.elmTool.hide(); 

    }

    handleCellPaste() {
        
        this.uo.saveForUndo(); 

        const util = this.util;

        util.saveSelection(); //required. Without this, CTRL-A (select element) & CTRL-V won't replace the element, but will paste at the end of the element.
        
        let contentword = document.querySelector('#idContentWord');
        if(contentword) contentword.parentNode.removeChild(contentword);
        
        var el;
        var curr;
        if (window.getSelection) {
            curr = window.getSelection().getRangeAt(0).commonAncestorContainer;
            if (curr.nodeType === 3) {  //ini text node
                el = curr.parentNode;
            } else {
                el = curr;
            }
        }
        else if (document.selection) {
            curr = document.selection.createRange();
            el = document.selection.createRange().parentElement();
        }

        var tmptop = el.getBoundingClientRect().top + window.pageYOffset;
        const html = '<div style="position:absolute;z-index:-1000;top:' + tmptop + 'px;left:-1000px;width:100px;height:100px;overflow:auto;" name="idContentWord" id="idContentWord" contenteditable="true"></div>';
        dom.appendHtml(this.builderStuff, html);

        contentword = document.querySelector('#idContentWord');
        contentword.focus();

        setTimeout(()=> {

            try {

                var sPastedText = '';
                
                let contentword = document.querySelector('#idContentWord');

                //Check video embed
                var bPasteObject = false;
                var src = contentword.innerText;
                
                if(!this.opts.disableAutoEmbedVideo) {
                    //var youRegex = /^http[s]?:\/\/(((www.youtube.com\/watch\?(feature=player_detailpage&)?)v=)|(youtu.be\/))([^#\&\?]*)/;
                    var youRegex = /^http[s]?:\/\/(((www.youtube.com\/watch\?(feature=player_detailpage&)?)v=)|(youtu.be\/))([^#&?]*)/;
                    var vimeoRegex = /^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/)|(video\/))?([0-9]+)\/?/;
                    var youRegexMatches = youRegex.exec(src);
                    var vimeoRegexMatches = vimeoRegex.exec(src);
                    if (youRegexMatches !== null || vimeoRegexMatches !== null) {
                        if (youRegexMatches !== null && youRegexMatches.length >= 7) {
                            var youMatch = youRegexMatches[6];
                            src = 'https://www.youtube.com/embed/' + youMatch + '?rel=0';
                        }
                        if (vimeoRegexMatches !== null && vimeoRegexMatches.length >= 7) {
                            var vimeoMatch = vimeoRegexMatches[6];
                            src = 'https://player.vimeo.com/video/' + vimeoMatch;
                        }
                        sPastedText = '<div class="embed-responsive embed-responsive-16by9"><iframe width="560" height="315" src="' + src + '" frameborder="0" allowfullscreen=""></iframe></div>';
                        bPasteObject = true;
                    }
                }

                if(!bPasteObject) {
                    if(this.opts.paste === 'text'){

                        let elms = contentword.querySelectorAll('p,h1,h2,h3,h4,h5,h6');
                        Array.prototype.forEach.call(elms, (elm) => {
                            elm.innerHTML = elm.innerHTML + ' '; //add space (&nbsp;)
                        });

                        sPastedText = contentword.innerText;

                    } else {

                        sPastedText = contentword.innerHTML;

                        if(this.opts.paste === 'html'){//with styles
                            sPastedText = util.cleanHTML(sPastedText, false);
                        } else { //html-without-styles (default)
                            sPastedText = util.cleanHTML(sPastedText, true);
                        }
                        contentword.innerHTML = sPastedText;


                        if(this.opts.paste === 'html'){//with styles

                            let elms = contentword.querySelectorAll('*');
                            Array.prototype.forEach.call(elms, (elm) => {
                                for(let n = 0;n<elm.attributes.length;n++) {
                                    if(elm.attributes[n].name!=='style') elm.removeAttribute(elm.attributes[n].name);
                                }
                            });

                            // Extra: 
                            // Remove li with white-space: pre;
                            elms = contentword.querySelectorAll('li');
                            Array.prototype.forEach.call(elms, (elm) => {
                                elm.style.whiteSpace = '';
                            });

                        } else { //html-without-styles (default)
                            
                            const removeAttributes = (element) => {
                                while (element.attributes.length > 0) {
                                    element.removeAttribute(element.attributes[0].name);
                                }
                            };
                            let elms = contentword.querySelectorAll('*');
                            Array.prototype.forEach.call(elms, (elm) => {
                                removeAttributes(elm);
                            });
                        }

                        // NOTE: paste <h1><p> jadi nempel

                        // NOTE ($editor => contentword)
                        // if($editor.children('p,h1,h2,h3,h4,h5,h6,ul,li').length>1){
                        //     //Fix text that doesn't have paragraph
                        //     $editor.contents().filter(function() {
                        //         return (this.nodeType === 3 && jQuery.trim(this.nodeValue)!=='');
                        //     }).wrap( "<p></p>" ).end().filter("br").remove();
                        // }
                
                    
                        // // Source: https://gist.github.com/sbrin/6801034 
                        // jQuery('p', $editor).each(function(){
                        //     var str = jQuery(this).attr('style');
                        //     var matches = /mso-list:\w+ \w+([0-9]+)/.exec(str);
                        //     if (matches) {
                        //         jQuery(this).data('_listLevel',  parseInt(matches[1], 10));
                        //     }
                        // });
                        // var last_level=0;
                        // var pnt = null;
                        // jQuery('p', $editor).each(function(){
                        //     var cur_level = jQuery(this).data('_listLevel');
                        //     if(cur_level !== undefined){
                        //         var txt = jQuery(this).text();
                        //         var list_tag = '<ul></ul>';
                        //         if (/^\s*\w+\./.test(txt)) {
                        //             var matches = /([0-9])\./.exec(txt);
                        //             if (matches) {
                        //                 var start = parseInt(matches[1], 10);
                        //                 list_tag = start>1 ? '<ol start="' + start + '"></ol>' : '<ol></ol>';
                        //             }else{
                        //                 list_tag = '<ol></ol>';
                        //             }
                        //         }

                        //         if(cur_level>last_level){
                        //             if(last_level===0){
                        //                 jQuery(this).before(list_tag);
                        //                 pnt = jQuery(this).prev();
                        //             }else{
                        //                 pnt = jQuery(list_tag).appendTo(pnt);
                        //             }
                        //         }
                        //         if(cur_level<last_level){
                        //             for(var i=0; i<last_level-cur_level; i++){
                        //                 pnt = pnt.parent();
                        //             }
                        //         }
                        //         jQuery('span:first', this).remove();
                        //         pnt.append('<li>' + jQuery(this).html() + '</li>');
                        //         jQuery(this).remove();
                        //         last_level = cur_level;
                        //     }else{
                        //         last_level = 0;
                        //     }
                        // });
                        // //jQuery('[style]', $editor).removeAttr('style'); //done (see cleanHTML)
                        // jQuery('[align]', $editor).removeAttr('align');
                        // //jQuery('span', $editor).replaceWith(function() {return jQuery(this).contents();}); //done (see cleanHTML)
                        // jQuery('span:empty', $editor).remove();
                        // //jQuery("[class^='Mso']", $editor).removeAttr('class'); //done (see cleanHTML)
                        // jQuery('p:empty', $editor).remove();
                    

                        sPastedText = contentword.innerHTML;

                    }
                }


                contentword = document.querySelector('#idContentWord');
                if(contentword) contentword.parentNode.removeChild(contentword);

                // LATER
                // jQuery('.cell-active').find('.elm-active').removeClass('elm-active');
                // jQuery("#divElementTool").css("display", "none"); //force hide ellement tool 

                util.restoreSelection();

                var oSel = window.getSelection();
                var range = oSel.getRangeAt(0);
                range.extractContents();
                range.collapse(true);
                var docFrag = range.createContextualFragment(sPastedText);
                var lastNode = docFrag.lastChild;

                range.insertNode(docFrag);
            
                // // Fix HTML structure (Sometimes h1 can be pasted inside p)
                // let elms = col.querySelectorAll('p,h1,h2,h3,h4,h5,h6');
                // Array.prototype.forEach.call(elms, (elm) => {
                //     let elmswithin = elm.querySelectorAll('p,h1,h2,h3,h4,h5,h6');
                //     Array.prototype.forEach.call(elmswithin, (elmwithin) => {
                //         elmwithin.outerHTML = elmwithin.innerHTML;
                //     });
                // });
                // LATER
                // $block.find('h1:empty,h2:empty,h3:empty,h4:empty,h5:empty,h6:empty,p:empty').remove();

                range.setStartAfter(lastNode);
                range.setEndAfter(lastNode);
                range.collapse(false);
                var comCon = range.commonAncestorContainer;
                if (comCon && comCon.parentNode) {
                    try { comCon.parentNode.normalize(); } catch (e) {
                        // Do Nothing
                    }
                }
                oSel.removeAllRanges();
                oSel.addRange(range);

                let builderActive = document.querySelector('.builder-active');
                if(builderActive) this.applyBehaviorOn(builderActive);

                //Trigger Change event
                this.opts.onChange();

                //Trigger Render event
                this.opts.onRender();
                
            } catch(e) {

                let contentword = document.querySelector('#idContentWord');
                if(contentword) contentword.parentNode.removeChild(contentword);

            }

        }, 800);

    }

    cellSelected() {
        const util = this.util;
        return util.cellSelected();
    }

    // https://stackoverflow.com/questions/16839698/jquery-getscript-alternative-in-native-javascript
    loadScript(source, beforeEl, async = true, defer = true) {
        return new Promise((resolve, reject) => {
            let script = document.createElement('script');
            const prior = beforeEl || document.getElementsByTagName('script')[0];
        
            script.async = async;
            script.defer = defer;
        
            function onloadHander(_, isAbort) {
                if (isAbort || !script.readyState || /loaded|complete/.test(script.readyState)) {
                    script.onload = null;
                    script.onreadystatechange = null;
                    script = undefined;
            
                    if (isAbort) { reject(); } else { resolve(); }
                }
            }
        
            script.onload = onloadHander;
            script.onreadystatechange = onloadHander;
        
            script.src = source;
            prior.parentNode.insertBefore(script, prior);
        });
    }
    
    // If scriptPath is not set, this will be used.
    currentScriptPath() {
        let filename, location;
        var scriptElements = document.getElementsByTagName('script');
        for (var i = 0; i < scriptElements.length; i++) {
            var source = scriptElements[i].src;
            filename = 'contentbuilder.js';
            if (source.indexOf(filename) > -1) {
                location = source.substring(0, source.indexOf(filename));
            }
            filename = 'contentbuilder.min.js';
            if (source.indexOf(filename) > -1) {
                location = source.substring(0, source.indexOf(filename));
            }
        }
        return location;
        // var scripts = document.querySelectorAll('script[src]');
        // var currentScript = scripts[scripts.length - 1].src;
        // var currentScriptChunks = currentScript.split('/');
        // var currentScriptFile = currentScriptChunks[currentScriptChunks.length - 1];
        // return currentScript.replace(currentScriptFile, '');
    }

}

export default ContentBuilder;

// (function(window){

//     var run = (opts = {}) => {
//         return new ContentBuilder(opts);
//     }

//     window.ContentBuilder = run;

// })(window);

// (function(window){

//     var $ = window.jQuery;
//     if($) $.contentbuilder = function (options) {
//         return new ContentBuilder(options);
//     };

// })(window);