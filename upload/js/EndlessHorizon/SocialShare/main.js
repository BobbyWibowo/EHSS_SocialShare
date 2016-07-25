var logPrefix = '[Endless Horizon] Social Share: ';

function out(m)
{
    if (ehss_settings["debug"]) { console.log(logPrefix + m); }
    return true;
}

function encodeURIComponentStrict(t)
{
    t = encodeURIComponent(t);
    t = t.replace('~' , '%7e'); // replace ~
    t = t.replace('\'', '%27'); // replace '
    return t;
}

$(document).ready(function()
{
    // initialize elements
    var sharePageButtons = $('.eh_socialshare'),
        sharePagePopup = $('<div class="eh_socialshare_popup"></div>'),
        shareCounts = $.parseJSON(ehss_settings["data-counts"]),
        enabledItemsCount = 0,
        enabledShareCounter = 0,
        tmp;
    
    // initialize popup
    sharePagePopup.attr('style', 'display: none');
    sharePagePopup.click(function(e)
    {
        if ($(this).css('opacity') == 1)
        {
            $(this).fadeToggle(400, "swing");
        }
    });
    
    $('body').append(sharePagePopup);
    
    // initialize popup items
    var shareItem, replacedURL, i, j, x, customFix;
    
    sharePagePopup.append($('<div class="items_container"><div class="centered"><ul></ul></div></div>'));
    
    for (i = 0; i < ehss_social_sites.length; i++)
    {
        if (!ehss_social_sites[i].disabled)
        {
            replacedURL = ehss_social_sites[i].popupURL;

            for (j = 0; j < ehss_replace_methods.length; j++)
            {
                if (typeof ehss_replace_methods[j].value === 'string')
                {
                    x = encodeURIComponentStrict(ehss_settings[ehss_replace_methods[j].value]);
                }
                else
                {
                    switch (ehss_replace_methods[j].value)
                    {
                        case 1  : x = encodeURIComponentStrict(document.title); // 1 - document.title
                            break;
                        // extend when necessary
                        default : x = '';
                    }
                }
                
                replacedURL = replacedURL.replace(ehss_replace_methods[j].key, x);
            }

            // extra patch for Pinterest
            if ((ehss_social_sites[i].popupName === 'pinterest') && (replacedURL.indexOf('&media=') === -1))
            {
                x = $(document).find('meta[property="og:image"]');
                out(x.length);
                if (x.length) { replacedURL += '&media=' + encodeURIComponentStrict(x[x.length - 1].getAttribute('content')); }
            }
            
            shareItem = $('<li><a></a></li>');
            shareItem.find('a').attr('href', replacedURL);
            shareItem.find('a').attr('data-name', ehss_social_sites[i].popupName);
            shareItem.find('a').attr('data-specs', ehss_social_sites[i].popupSpecs);
            
            customFix = ehss_social_sites[i].customFix;
            switch (customFix)
            {
                case 1 : shareItem.find('a').removeAttr('href');
                         shareItem.find('a').attr('data-href', replacedURL);
                         shareItem.find('a').attr('data-custom-fix', customFix);
                         out('Applied custom fix #1: "Prevent pinit.js from hijacking" to an item.');
                    break;
                // extend when necessary
            }
            
            shareItem.find('a').append($('<span style="background-color: ' + ehss_social_sites[i].bgColor + '"></span>'));
            shareItem.find('a').find('span').append($('<i class="' + ehss_social_sites[i].iconClass + '"></i>'));
            
            if (shareCounts && (typeof ehss_social_sites[i].shareCountID === 'string') && (ehss_social_sites[i].shareCountID.length > 0))
            {
                x = shareCounts[ehss_social_sites[i].shareCountID];
                if (x >= 0)
                {
                    if (x === 1)
                    {
                        tmp = ehss_settings['data-count-title-singular'];
                    }
                    else
                    {
                        tmp = ehss_settings['data-count-title-plural'];
                    }

                    shareItem.find('a').find('span').append($('<i class="shareCount" style="color: ' + ehss_social_sites[i].bgColor + '" title="' + tmp.replace('{x}', x) +'">' + x + '</i>'));
                    enabledShareCounter += 1;
                }
            }
            
            shareItem.append($('<span>' + ehss_social_sites[i].name + '</span>'));
            
            sharePagePopup.find('.items_container .centered ul').append(shareItem);
            
            enabledItemsCount += 1;
        }
    }
    
    // prevent items from trigerring its parent's event handlers
    sharePagePopup.find('.items_container .centered ul li').click(function(e){ e.stopPropagation(); });
    
    // register click event handler to all items
    sharePagePopup.find('.items_container .centered ul li a').click(function(e)
    {
        e.preventDefault();
        
        customFix = parseInt($(this).attr('data-custom-fix'));
        if (customFix)
        {
            switch (customFix)
            {
                case 1 : window.open($(this).attr('data-href'), $(this).attr('data-name'), $(this).attr('data-specs'));
                    break;
                // extend when necessary
            }

            return;
        }
        
        window.open($(this).attr('href'), $(this).attr('data-name'), $(this).attr('data-specs'));
    });
    
    sharePageButtons.click(function(e)
    {
        e.preventDefault();
        if (sharePagePopup.css('display') == 'none')
        {
            sharePagePopup.fadeToggle(400, "swing");
        }
    });
    
    out(enabledItemsCount + ' item(s) for the social share widget were initialized.');
    out('Share counter was initialized for ' + enabledShareCounter + ' item(s).');
});