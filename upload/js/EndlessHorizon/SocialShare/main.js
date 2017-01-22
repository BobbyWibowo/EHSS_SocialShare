/*
 * EH_SocialShare
 * by Bobby Wibowo
 */

$(document).ready(function()
{
    /** General **/

    if (!EHSS_sites || !Object.keys(EHSS_sites).length) { return; }

    var body = $('body');

    function EHSS_log(m)
    {
        if (EHSS_settings.debug) { console.log('[Endless Horizon] Social Share: ' + m); }
        return true;
    }

    // Check the existence of settings
    if (!EHSS_settings || !Object.keys(EHSS_settings).length) { EHSS_log('Failing.. Could not load settings.'); return; }

    // PopupCenter function, courtesy of http://www.xtf.dk/2011/08/center-new-popup-window-even-on.html
    function PopupCenter(url, w, h)
    {
        var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left,
            dualScreenTop = window.screenTop != undefined ? window.screenTop : screen.top,
            width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width,
            height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height,
            left = ((width / 2) - (w / 2)) + dualScreenLeft,
            top = ((height / 2) - (h / 2)) + dualScreenTop,
            popup = window.open(url, null, 'toolbar=0, status=0, scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);

        if (window.focus) { popup.focus(); }
    }

    // Replace chars: ~ '
    function encodeURIComponentStrict(t) { return encodeURIComponent(t).replace('~' , '%7e').replace('\'', '%27'); }

    function makePopupLink(s, url)
    {
        var api = EHSS_sites[s].url;

        // Patch for Pinterest
        if (EHSS_sites[s].pin && (api.indexOf('&media=') === -1))
        {
            x = $('head').find('meta[property="og:image"]');
            if (x.length) { api += '&media=' + encodeURIComponentStrict($(x[0]).attr('content')); }
        }

        return api.replace('{url}', encodeURIComponentStrict(url)).replace('{title}', encodeURIComponentStrict(document.title));
    }

    // Initialize main variables
    var overlay = $('<div class="ehss_overlay" style="display: none"><div class="ehss_items"><div class="ehss_inner"><ul></ul></div></div></div>'),
        shareCounts = $.parseJSON(EHSS_settings.counts.replace(/&quot;/g, "\"")),
        eItems = 0, eShareCounters = 0, trigger;
    
    for (s in EHSS_sites)
    {
        var item = $('<li></li>'),
            item_content = '',
            item_count = '',
            scid = EHSS_sites[s].scid;

        if (shareCounts && shareCounts.hasOwnProperty(scid))
        {
            var x = shareCounts[scid],
                t = EHSS_settings[(x === 1 ? 'count-s' : 'count-p')];

            item_count +='<i class="ehss_count" style="color: ' + EHSS_sites[s].bg + '" title="' + t.replace('{x}', x) +'">' + x + '</i>';
            eShareCounters += 1;
        }

        item_content += '<a data-id="' + s + '">' +
                '<span style="background-color: ' + EHSS_sites[s].bg + '"><i class="' + EHSS_sites[s].ic + '"></i>' +
                    item_count +
                '</span>' +
            '</a>' +
            '<span>' + s + '</span>';

        item.append(item_content);
        overlay.find('.ehss_items .ehss_inner ul').append(item);
        eItems += 1;
    }

    // Register click handler to the parent (should be triggered whenever someone click on the transparent background)
    overlay.click(function(e) { if (parseInt($(this).css('opacity')) === 1) { $(this).fadeToggle(400, "swing"); } });
    
    // Prevent items from trigerring its parent's event handlers
    overlay.find('.ehss_items .ehss_inner ul li').click(function(e){ e.stopPropagation(); });
    
    // Register click event handler to all items
    overlay.find('.ehss_items .ehss_inner ul li a').click(function(e)
    {
        e.preventDefault();

        var id = $(this).data('id'),
            x = makePopupLink(id, $(trigger).data('permalink') || EHSS_settings.url),
            blank = EHSS_sites[id].pb;

        if (blank)
        {
            window.open(x, '_blank');
        }
        else
        {
            PopupCenter(x, EHSS_sites[id].dim.w, EHSS_sites[id].dim.h);
        }
    });

    body.append(overlay);
    
    // Attach handler on body element with '.eh_socialshare' filter so that it'll work with dynamically generated buttons
    body.on('click', '.ehss_button', function(e)
    {
        e.preventDefault();

        if (overlay.css('display') == 'none') {
            trigger = this;
            // Hide share count if the trigger button was from post's permalink (since the share counts represents the whole thread)
            overlay.find('.ehss_count').attr('style', ($(trigger).data('permalink') ? 'display: none' : ''));
            overlay.fadeToggle(400, "swing");
        }
    });
    
    EHSS_log(eItems + ' item' + (eItems === 1 ? '' : 's') + ' for the social share widget were initialized.');
    EHSS_log('Share counter was ' + (eShareCounters ? 'initialized for ' + eShareCounters + ' item' + (eShareCounters === 1 ? '' : 's') : 'not initialized for any items') + '.');

    
    /** Animation for Floating Share Button **/

    if (EHSS_settings.fsbanim)
    {
        var btn = body.find('.ehss_button.floating'),
            iw = btn.innerWidth() + 1,
            dw = btn.data('w'),
            btns = btn.find('span'),
            animTime = 400;

        // Shrink and show button after capturing the targeted full width
        btns.css('opacity', 0);
        btn.css('width', dw);
        btn.css('opacity', 1);

        btn.hover(
            function(e) // mouse enter
            {
                $(this).animate({
                    width: iw + 'px'
                }, animTime);
                btns.animate({
                    opacity: 1
                }, animTime);
            },
            function(e) // mouse leave
            {
                $(this).animate({
                    width: dw
                }, animTime);
                btns.animate({
                    opacity: 0
                }, animTime);
            }
        );
    }
});