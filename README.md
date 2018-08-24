Enveloper
=========

- This project can download emails from Gmail and save them as Html files. PhantomJS will be used to convert HTML file to PDF.
- Please follow Gmail PHP API to access the account. 
- `client_secret.json` should have gmail account credentials
- `createPdfWkHtml` function can be used to convert if you have installed Wkhtmltopdf in your machinve.

## Commands

- To print commands
``` php enveloper.php --printCommands ```

- To run commands one by one
``` php enveloper.php --runCommands ```

- Get Gmails between specific dates
``` php enveloper.php --start 2014/7/1 --end 2018/6/1 ```

- Convert all HTML files in a folder to PDF files in PDF folder
- `pdf.js` is configuration file that needed to be updated as per requirements
``` php pdf.php HTML ```
