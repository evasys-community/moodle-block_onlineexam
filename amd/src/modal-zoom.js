define(['core/templates', 'core/str'], function(templates) {

    var modalTitle = '';
    var modalZoomSelector = '#block_onlineexam_exams_content';

    var popupinfotitle = '';
    var popupinfocontent = '';
    var userlogintime = 0;

    var doRefresh = function() {
        var myElement = document.getElementById("block_onlineexam_contentframe");
        if (myElement) {
            var oldsrc = myElement.src;
            myElement.src = '';
            myElement.src = oldsrc;
        }
    };

    var handleClickSmallModal = function(e) {
        e.preventDefault();

        var modalZoomElem = e.target;

        var originalIframe = modalZoomElem.querySelector('iframe');

        var templatePromise = null;

        if (originalIframe !== null) {
            // Open from Moodle page, i.e., onlineexam iframe exists.
            templatePromise = templates.render('block_onlineexam/modal-iframe', {
                // Copy iframe target URL from block, but inform that now in modalZoom window.
                src: originalIframe.src + "&modalZoom=1",
                title: modalTitle
            });
        } else {
            // Open from iframe, i.e., needs to switch to parent Moodle page.
            originalIframe = parent.document.querySelector('iframe');
            templatePromise = templates.render('block_onlineexam/modal-iframe', {
                // Copy iframe target URL from block, but inform that now in modalZoom window.
                src: originalIframe.src + "&modalZoom=1",
                title: modalTitle
            });
        }

        templatePromise.done(function(source) {

            var div = document.createElement('div');
            div.innerHTML = source;

            var modalContainer = div.firstChild;

            document.body.insertBefore(modalContainer, document.body.firstChild);
            document.body.className += ' block_onlineexam_custom-modal-open';

            var closeCallback = function() {

                document.body.className = document.body.className.replace(' block_onlineexam_custom-modal-open', '');

                doRefresh();

                modalContainer.className += ' fading';

                setTimeout(function() {
                    if (modalContainer.parentNode !== null) {
                        modalContainer.parentNode.removeChild(modalContainer);
                    }
                }, 250);
            };

            modalContainer.querySelector('.block_onlineexam_custom-modal_close-button')
                .addEventListener('click', function(e) {
                    e.preventDefault();
                    return closeCallback(e);
                });

            modalContainer.addEventListener('click', function(e) {
                e.preventDefault();

                return e.target !== modalContainer ?
                    false : closeCallback(e);
            });
        });
    };

    return {
        init: function(popuptitle, popupcontent, currentlogin) {

            popupinfotitle = popuptitle;
            popupinfocontent = popupcontent;
            userlogintime = currentlogin;

            var zoomContainer = document.querySelectorAll(modalZoomSelector);

            for (var i = 0; i < zoomContainer.length; i++) {
                zoomContainer[i].addEventListener('click', handleClickSmallModal);
            }

            // Namespace in window for EVAEXAM-functions etc.
            window.EVAEXAM = {
                // Define "global" functions in namespace -> later "external" access from iframe possible.
                generatepopupinfo: this.generatepopupinfo
            };
            window.evaexamGeneratePopupinfo = this.generatepopupinfo;
        },
        generatepopupinfo: function() {

            // Get saved data from sessionStorage.
            var popupinfo = sessionStorage.getItem('onlineexam_popupinfo');

            if (popupinfo == false || popupinfo === null || popupinfo != userlogintime) {

                // Save data to sessionStorage.
                sessionStorage.setItem('onlineexam_popupinfo', userlogintime);

                var templatePromise = templates.render('block_onlineexam/popupinfo', {
                    title: popupinfotitle,
                    content: popupinfocontent
                });

                templatePromise.done(function(source) {

                    var div = document.createElement('div');
                    div.innerHTML = source;

                    var modalContainer = div.firstChild;

                    document.body.insertBefore(modalContainer, document.body.firstChild);
                    document.body.className += ' block_onlineexam_custom-modal-open popupinfo';

                    var closeCallback = function() {

                        document.body.className = document.body.className.replace(' block_onlineexam_custom-modal-open', '');

                        modalContainer.className += ' fading';

                        setTimeout(function() {
                            if (modalContainer.parentNode !== null) {
                                modalContainer.parentNode.removeChild(modalContainer);
                            }
                        }, 250);
                    };

                    modalContainer.querySelector('.block_onlineexam_custom-modal_close-button')
                    .addEventListener('click', function(e) {
                        e.preventDefault();
                        return closeCallback(e);
                    });

                    modalContainer.addEventListener('click', function(e) {
                        e.preventDefault();

                        return e.target !== modalContainer ?
                                false : closeCallback(e);
                    });
                });
            }
        }
    };
});
