# com.uk.andyw.pdfreceipt

PDF Receipt extension for CiviCRM

## Configuration
**FIXME: Currently this extension is hardcoded in pdfreceipt.php pdfreceipt_get_format to 'SOM' template.**

Templates are stored in [extdir]/receipt_tpl/[template_name]

## Testing
The URL https://example.org/civicrm/admin/receipt/preview can be used with parameters:
* tpl_name: template name (eg. SOM)
* pid: Participant ID (to generate an event receipt)
* mid: Membership ID (to generate a membership receipt)
* ctid: Contribution ID (to generate a contribution receipt)
* cid: Contact ID (to generate a receipt based on contact ID, latest contribution/membership etc. (a bit of guesswork is done by the code)).

#### Example:
https://example.org/civicrm/admin/receipt/preview?tpl_name=SOM&mid=39

