# Single Sign-On for GLPI

![Lint](https://github.com/edgardmessias/glpi-singlesignon/workflows/Lint/badge.svg)
[![CodeFactor](https://www.codefactor.io/repository/github/edgardmessias/glpi-singlesignon/badge)](https://www.codefactor.io/repository/github/edgardmessias/glpi-singlesignon)
[![Total Downloads](https://img.shields.io/github/downloads/edgardmessias/glpi-singlesignon/total.svg)](https://github.com/edgardmessias/glpi-singlesignon/releases)
[![Current Release](https://img.shields.io/github/release/edgardmessias/glpi-singlesignon.svg)](https://github.com/edgardmessias/glpi-singlesignon/releases/latest)

Single sign-on (SSO) is a property of access control of multiple related, yet independent, software systems. With this property, a user logs in with a single ID and password to gain access to any of several related systems.

# Installation
 * Uncompress the archive to the `<GLPI_ROOT>/plugins/singlesignon` directory
 * Navigate to the Configuration > Plugins page,
 * Install and activate the plugin.

# Usage
 * Go to `Configuration > Single Sign-On` and add a provider
 * To test, do logout and try login with links below login page `Login with <name>`

# Available providers
 * Azure - https://docs.microsoft.com/azure/app-service/configure-authentication-provider-aad
 * Facebook - https://developers.facebook.com/docs/apps/
 * GitHub - https://developer.github.com/apps/building-oauth-apps/creating-an-oauth-app/
 * Google - https://developers.google.com/identity/protocols/OpenIDConnect
 * Instagram - https://www.instagram.com/developer/authentication/
 * LinkedIn - https://docs.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?context=linkedin/context
 * Generic - Allow to define custom URLs
 * Zitadel - use _Generic_ and see parameters in [Generic Examples - Zitadel](generic_examples/zitadel.md)

# Adding translations
If your preferred language is missing. You can add your own translation with the following steps:
 * Go to the plugin folder
 * Switch to the folder locales
 * Copy one of the already existing .po files
 * Rename it into the correct notation of your language
 * Edit the file, edit msgstr to change the translation, do not touch the msgid
 * Edit the header especially the "Language: "
 * When the file is ready, then you need to compile it with: msgfmt -o filename.mo filename.po
 * If msgfmt is not found, install the package gettext (apt install -y gettext)
 * If you edit a previous translation, you may need to update the translation cache: go to Setup - General - Performance, enable Debug mode, clear translation cache

# Screenshots

![image 1](./screenshots/image_1.png)
![image 2](./screenshots/image_2.png)

# Donation
<table border="0">
 <tr>
    <td align="center">
    PayPal <br>
       <img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=https://www.paypal.com/donate?hosted_button_id=5KHYY5ZDTNDSY"> <br>
       <a href="https://www.paypal.com/donate?hosted_button_id=5KHYY5ZDTNDSY">
          <img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif">
       </a>
    </td>
    <td align="center">
       Pix (Brazil) <br>
       <img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=00020126680014BR.GOV.BCB.PIX013628571c52-8b9b-416c-a18f-8e52460608810206Doa%C3%A7%C3%A3o5204000053039865802BR5923Edgard%20Lorraine%20Messias6009SAO%20PAULO61080540900062160512NU50UnEaVM0H63042A45"> <br>
       28571c52-8b9b-416c-a18f-8e5246060881
    </td>
 </tr>
</table>
