#!/bin/bash

soft='singlesignon'
email='@edgardmessias'
copyright='glpi-singlesignon Development Team'

# Only strings with domain specified are extracted (use Xt args of keyword param to set number of args needed)

xgettext *.php */*.php --copyright-holder="$copyright" --package-name="$soft" -o locales/singlesignon.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po  \
	--keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t \
	--keyword=_ex:1c,2,3t --keyword=_nx:1c,2,3,5t --keyword=_sx:1c,2,3t --keyword=__sso:1
