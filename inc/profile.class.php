<?php

class PluginSinglesignonProfile extends CommonDBTM {

    const READMY = 1;
    const CHANGENAME = 1024;

    //Não usa tabela própria
    static protected $notable = true;

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

        if (!$withtemplate) {
            if ($item->getType() == 'Profile') {
                return __('Live Helper Chat');
            }
        }
        return '';
    }

    function getRights($interface = 'central') {
        $values = [
            self::READMY     => 'Visualizar',
            self::CHANGENAME => 'Alterar Nome',
        ];
        return $values;
    }

    /**
     *
     * @param Profile $profile
     */
    function showForm($profile) {
        $canedit = Session::haveRightsOr(Profile::$rightname, [CREATE, UPDATE, PURGE]);

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form name='form' action=\"" . Toolbox::getItemTypeFormURL('Profile') . "\" method='post'>";
        }

        $matrix_options = [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
        ];

        $rights = [
            [
                'rights' => Profile::getRightsFor('PluginLivehelperchatProfile', 'central'),
                'label'  => 'Widget',
                'field'  => 'plugin_livehelperchat_widget'
            ],
            [
                'rights' => Profile::getRightsFor('PluginLivehelperchatChat', 'central'),
                'label'  => 'Visualizar Chat do Ticket',
                'field'  => 'plugin_livehelperchat_chat'
            ],
        ];

        $matrix_options['title'] = 'Live Helper Chat';
        $profile->displayRightsChoiceMatrix($rights, $matrix_options);

        if ($canedit) {
            //Botão
            echo "<div class='center'>";
            echo "<input type='hidden' name='id' value='" . $profile->fields['id'] . "'>";
            echo "<input type='submit' name='update' value=\"" . _sx('button', 'Save') . "\" class='submit'>";
            echo "</div>\n";
            Html::closeForm();
        }

        echo "</div>";
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            $config = new self();
            $config->showForm($item);
        }
        return true;
    }

    static function install() {
        $profile_widget = 'plugin_livehelperchat_widget';
        $profile_chat = 'plugin_livehelperchat_chat';

        $profileRight = new ProfileRight();

        $profiles = $profileRight->find("`name` = 'livehelperchat'", '', '');
        //Se existir permissões antigas, renomea-las
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profile['name'] = $profile_widget;
                $profileRight->update($profile);
            }
        }

        /**
         * Widget
         */
        $profileWidget = $profileRight->find("`name` = '$profile_widget'", '', 1);
        //Caso não existir, criar novos profiles
        if (empty($profileWidget)) {
            ProfileRight::addProfileRights([$profile_widget]);
            ProfileRight::updateProfileRightAsOtherRight($profile_widget, PluginLivehelperchatProfile::READMY, '');
            ProfileRight::updateProfileRightAsOtherRight($profile_widget, PluginLivehelperchatProfile::CHANGENAME, '');
        }

        /**
         * Chat Ticket
         */
        $profileChat = $profileRight->find("`name` = '$profile_chat'", '', 1);
        //Caso não existir, criar novos profiles
        if (empty($profileChat)) {
            ProfileRight::addProfileRights([$profile_chat]);
            ProfileRight::updateProfileRightAsOtherRight($profile_chat, READ, '');
        }
    }

    static function uninstall() {
        ProfileRight::deleteProfileRights(['plugin_livehelperchat_widget']);
    }

}
