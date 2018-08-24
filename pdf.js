    var page = require('webpage').create(),
        system = require('system'),
        fs = require('fs');

    page.paperSize = {
        format: 'A4',
        orientation: 'portrait',
        /*margin: {
            top: "1cm",
            bottom: "1cm"
        }*/
    };

    // This will fix some things that I'll talk about in a second
    page.settings.dpi = "96";

    page.content = fs.read(system.args[1]);

    var output = system.args[2];

    window.setTimeout(function () {
        page.render(output, {format: 'pdf'});
        phantom.exit(0);
    }, 2000);