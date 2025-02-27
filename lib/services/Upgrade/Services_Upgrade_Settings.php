<?php

class Services_Upgrade_Settings
{
    private $_settings;

    public function __construct(Dao_Factory $daoFactory, Services_Settings_Container $settings)
    {
        $this->_settings = $settings;
    }

    // ctor

    public function update()
    {
        /*
         * Make sure some versionnumbers are always in the db, so
         * comparisons always work
         */
        $this->setIfNot('settingsversion', '0.00');
        $this->setIfNot('securityversion', '0.00');

        if ($this->_settings->get('settingsversion') < 0.15) {
            $this->remove('system_languages');
        } // if

        $this->createServerKeys($this->_settings->get('openssl_cnf_path'));
        $this->createPasswordSalt();
        $this->setupNewsgroups();
        $this->createRsaKeys();
        $this->createXsrfSecret();
        $this->remove('sabnzbdurltpl');
        $this->remove('sabnzbdurl');
        $this->remove('recompress_nzb');
        $this->remove('available_languages');
        $this->remove('featureversion');
        $this->remove('max_newcount');
        $this->remove('action');
        $this->remove('submitedit');
        //Assure usage of the newer black/whitelists when settingsversion is lower then 38.
        if ($this->_settings->get('settingsversion') < 0.38) {
            $this->remove('blacklist_url');
            $this->remove('whitelist_url');
        } // if

        $this->setIfNot('cookie_expires', 30);
        $this->setIfNot('sendwelcomemail', true);
        $this->setIfNot('twitter_consumer_key', 'LRJCpeHASigYtWEmxoNPA');
        $this->setIfNot('twitter_consumer_secret', 'QvwZglJNpzAnoVDt40uUyu5dRDlVFVs4ddxfEkYp7A'); // This secret can be shared
        $this->setIfNot('auditlevel', 0); // No auditing
        $this->setIfNot('system_languages', ['nl_NL' => 'Nederlands', 'en_US' => 'English', 'tr_TR' => 'Turkish', 'fr_FR' => 'French']);
        $this->setIfNot('retention', 0);
        $this->setIfNot('retentiontype', 'fullonly');
        $this->setIfNot('deny_robots', true);
        $this->setIfNot('nntp_nzb', ['host' => '', 'user' => '', 'pass' => '', 'enc' => false, 'port' => 119, 'buggy' => false, 'verifyname' => true]);
        $this->setIfNot('nntp_hdr', ['host' => '', 'user' => '', 'pass' => '', 'enc' => false, 'port' => 119, 'buggy' => false, 'verifyname' => true]);
        $this->setIfNot('nntp_post', ['host' => '', 'user' => '', 'pass' => '', 'enc' => false, 'port' => 119, 'buggy' => false, 'verifyname' => true]);
        $this->setIfNot('smtp', ['use' => false, 'host' => '', 'user' => '', 'pass' => '', 'port' => 587]);
        $this->setIfNot('retrieve_newer_than', 0);
        $this->setIfNot('retrieve_full', false);
        $this->setIfNot('prefetch_image', false);
        $this->setIfNot('prefetch_nzb', false);
        $this->setIfNot('retrieve_comments', true);
        $this->setIfNot('retrieve_full_comments', false);
        $this->setIfNot('retrieve_reports', true);
        $this->setIfNot('retrieve_increment', 5000);
        $this->setIfNot('spot_moderation', 'act');
        $this->setIfNot('prepare_statistics', false);
        $this->setIfNot('external_blacklist', true);
        $this->setIfNot('blacklist_url', 'https://spotlist.store/spotnet/blacklist.xml');
        $this->setIfNot('external_whitelist', true);
        $this->setIfNot('whitelist_url', 'https://spotlist.store/spotnet/whitelist.xml');
        $this->setIfNot('enable_timing', false);
        $this->setIfNot('cache_path', './cache');
        $this->setIfNot('enable_stacktrace', false);
        $this->setIfNot('systemfrommail', 'spotweb@example.com');
        $this->setIfNot('customcss', '');
        $this->setIfNot('systemtype', 'public');
        $this->setIfNot('imageover_subcats', true);
        $this->setIfNot('highlight', true);
        $this->setIfNot('highcount', 10);
        $this->setIfNot(
            'newuser_grouplist',
            [
                ['groupid' => 2, 'prio' => 1],		// Group ID 2 == Anonymous users, open system
                ['groupid' => 3, 'prio' => 2],		// Group ID 3 == Authenticated users
            ]
        );
        $this->setIfNot('nonauthenticated_userid', 1);
        $this->setIfNot('custom_admin_userid', 2);
        $this->setIfNot(
            'valid_templates',
            [
                'we1rdo' => 'we1rdo',
            ]
        );
        $this->setIfNot('ms_translator_subscriptionkey', '');
        $this->remove('ms_translator_clientid');
        $this->remove('ms_translator_clientsecret');

        $this->updateSettingsVersion();
    }

    // update()

    /*
     * Create a setting only if no other value is set
     */
    public function setIfNot($name, $value)
    {
        if ($this->_settings->exists($name)) {
            return;
        } // if

        $this->_settings->set($name, $value);
    }

    // setIfNot

    /*
     * Remove a setting, silently fails if not set
     */
    public function remove($name)
    {
        $this->_settings->remove($name);
    }

    // remove

    /*
     * Update the current settingsversion number
     */
    public function updateSettingsVersion()
    {
        $this->_settings->set('settingsversion', SPOTWEB_SETTINGS_VERSION);
    }

    // updateSettingsVersion

    /*
     * Create the server private and public keys
     */
    public function createServerKeys($openSslCnfPath)
    {
        $spotSigning = Services_Signing_Base::factory();
        $x = $spotSigning->createPrivateKey($openSslCnfPath);

        $this->setIfNot('publickey', $x['public']);
        $this->setIfNot('privatekey', $x['private']);
    }

    // createServerKeys

    /*
     * Create the RSA keys
     */
    public function createRsaKeys()
    {
        /*
         * RSA Keys
         *
         * These are used to validate spots and moderator messages
         */
        $rsaKeys = [];
        $rsaKeys[2] = ['modulo' => 'ys8WSlqonQMWT8ubG0tAA2Q07P36E+CJmb875wSR1XH7IFhEi0CCwlUzNqBFhC+P',
            'exponent'          => 'AQAB', ];
        $rsaKeys[3] = ['modulo' => 'uiyChPV23eguLAJNttC/o0nAsxXgdjtvUvidV2JL+hjNzc4Tc/PPo2JdYvsqUsat',
            'exponent'          => 'AQAB', ];
        $rsaKeys[4] = ['modulo' => '1k6RNDVD6yBYWR6kHmwzmSud7JkNV4SMigBrs+jFgOK5Ldzwl17mKXJhl+su/GR9',
            'exponent'          => 'AQAB', ];

        $this->setIfNot('rsa_keys', $rsaKeys);
    }

    // createRsaKeys

    /*
     * Create an xsrf secret
     */
    public function createXsrfSecret()
    {
        $secret = substr(Services_User_Util::generateUniqueId(), 0, 8);

        $this->setIfNot('xsrfsecret', $secret);
    }

    // createXsrfSecret

    /*
     * Create the servers' password salt
     */
    public function createPasswordSalt()
    {
        $salt = Services_User_Util::generateUniqueId().Services_User_Util::generateUniqueId();

        $this->setIfNot('pass_salt', $salt);
    }

    // createPasswordSalt

    /*
     * Define the standard Spotnet groups
     */
    public function setupNewsgroups()
    {
        $this->setIfNot('hdr_group', 'free.pt');
        $this->setIfNot('nzb_group', 'alt.binaries.ftd');
        $this->setIfNot('comment_group', 'free.usenet');
        $this->setIfNot('report_group', 'free.willey');
    }

    // setupNewsgroups()

    /*
     * Change a systems' systemtype
     */
    public function setSystemType($systemType)
    {
        /*
         * Depending on the system type, we will have to make some additional
         * adjustments.
         */
        switch ($systemType) {
            case 'public':
                // Public sites should be indexable by a search engine
                $this->_settings->set('deny_robots', false);

                // Public systems should not show a public visible stack trace either
                $this->setIfNot('enable_stacktrace', true);

                // Reset the new users' group membership, id 2 is anonymous, 3 = authenticated
                $this->_settings->set('newuser_grouplist', [['groupid' => 2, 'prio' => 1], ['groupid' => 3, 'prio' => 2]]);

                /*
                 * The default anonymous user should be set to 1
                 */
                $this->_settings->set('nonauthenticated_userid', 1);

                break;
                // public

            case 'shared':
                // Shared sites might be indexable by a search engine
                $this->_settings->set('deny_robots', false);

                // Reset the new users' group membership, id 2 is anonymous, 3 = authenticated, 4 = trusted
                $this->_settings->set('newuser_grouplist', [
                    ['groupid' => 2, 'prio' => 1],
                    ['groupid' => 3, 'prio' => 2],
                    ['groupid' => 4, 'prio' => 3],
                ]);

                /*
                 * The default anonymous user should be set to 1, and this user is only placed
                 * into the closed system group
                 */
                $this->_settings->set('nonauthenticated_userid', 1);

                break;
                // shared

            case 'single':
                // Private/single owner sites should not be indexable by a search engine
                $this->_settings->set('deny_robots', true);

                // Reset the new users' group membership, id 2 is anonymous, 3 = authenticated, 4 = trusted, 5 = admin
                $this->_settings->set('newuser_grouplist', [
                    ['groupid' => 2, 'prio' => 1],
                    ['groupid' => 3, 'prio' => 2],
                    ['groupid' => 4, 'prio' => 3],
                    ['groupid' => 5, 'prio' => 4],
                ]);

                // The default anonymous user should be set to the custom admin use
                $this->_settings->set('nonauthenticated_userid', $this->_settings->get('custom_admin_userid'));

                break;
                // single

            default: throw new Exception("Unknown system type defined: '".$systemType."'");
        } // switch

        // Update the systems' type to our given setting
        $this->_settings->set('systemtype', $systemType);
    }

    // setSystemType
} // Services_Upgrade_Settings
