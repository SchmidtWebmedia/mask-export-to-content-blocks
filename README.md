# mask-export-to-content-blocks
TYPO3 extension for semi-automatic migration of your mask export extension to ContentBlocks

You have your own mask_export extension and you want to use ContentBlocks now? With this extension you are able to do a semi-automication migration of your used fields.
  

<a href="https://www.buymeacoffee.com/schmidtwebmedia" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;">
</a>

## Install
```bash
composer require schmidtwebmedia/mask-export-to-content-blocks --dev
```


## Description

The migration commands are executed on CLI.

> Important Step: Create Backup from your current database before you execute these CLI commands.

> It does not create any ContentBlock Elements, you have to create it on your own. The only thing what happens here is, that the mapping will be updated.

### Step 1

To create a _migration.json_ based on you **already existing** mask.json in your _mask_export extension_:

```bash
vendor/bin/typo3 mask-export-to-content-blocks:prepare --path="packages/cceexport/Configuration/Mask/mask.json"
```

> Pass the path to your mask.json

***

### Step 2
After execution was succesful, you will find the migration.json in this extension:
`fileadmin/mask_export_to_content_blocks/migration.json`

There you have to maintain your ContentBlock CEs and fields.
e.g.
```json
{
  "cceexport_banner": {
    "contentBlock": "schmidtwebmedia_banner",
    "mask": "cceexport_banner",
    "fields": [
      {
        "mask": "tx_cceexport_box_image",
        "contentBlock": "image",
        "type": "file",
        "ignore": false
      },
      {
        "mask": "tx_cceexport_box_title",
        "contentBlock": "header",
        "type": "text",
        "ignore": false
      }
    ]
  },
  "cceexport_social_media": {
    "contentBlock": "schmidtwebmedia_socialmedia",
    "mask": "cceexport_social_media",
    "fields": [
      {
        "mask": "tx_cceexport_social_media_links",
        "contentBlock": "schmidtwebmedia_socialmedia_social_media_links",
        "ignore": false,
        "table": {
          "fields": [
            {
              "contentBlock": "url",
              "mask": "tx_cceexport_url",
              "ignore": false
            },
            {
              "contentBlock": "channel",
              "mask": "tx_cceexport_channel",
              "ignore": false,
              "remapping": {
                "1": "facebook",
                "2": "instagram",
                "3": "twitter",
                "4": "youtube"
              }
            },
            {
              "mask": "tx_cceexport_title",
              "ignore": true
            }
          ]
        }
      }
    ]
  }
}
```

***

### Step 3

If all is maintained, you have to execute:
```bash
vendor/bin/typo3 mask-export-to-content-blocks:migrate  
```

#### What happens now?
1) tt_content CType will be replaced with new ContentBlock CEs
2) tt_content columns will be copied to the new ones
3) Sys file references will be updated to new CE
4) Foreign tables will be updated
5) be_groups will be updated
