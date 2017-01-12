<?php

namespace luya\crawler\models;

use Yii;
use luya\crawler\admin\Module;
use luya\crawler\models\Searchdata;

class Index extends \luya\admin\ngrest\base\NgRestModel
{
    /* yii model properties */

    public static function tableName()
    {
        return 'crawler_index';
    }

    public function scenarios()
    {
        return [
            'default' => ['url', 'content', 'title', 'language_info', 'added_to_index', 'last_update', 'url_found_on_page', 'group', 'description'],
            'restcreate' => ['url', 'content', 'title', 'language_info', 'url_found_on_page', 'last_update', 'url_found_on_page', 'added_to_index', 'group'],
            'restupdate' => ['url', 'content', 'title', 'language_info', 'url_found_on_page', 'last_update', 'url_found_on_page', 'added_to_index', 'group'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'url' => Module::t('index_url'),
            'title' => Module::t('index_title'),
            'language_info' => Module::t('index_language_info'),
            'content' => Module::t('index_content'),
            'url_found_on_page' => Module::t('index_url_found'),
            'added_to_index' => ' add to index on',
            'last_update' => 'last update'
        ];
    }
    
    /**
     * 
     * @param unknown $item
     * @param unknown $index
     */
    private static function indexer($item, &$index)
    {
        if (empty($index)) {
            $index = $item;
        } else {
            foreach ($index as $k => $v) {
                if (!array_key_exists($k, $item)) {
                    unset($index[$k]);
                }
            }
        }
    }

    /**
     * Search by query in an iteligent way
     * @param unknown $query
     * @param unknown $languageInfo
     * @param string $returnQuery
     * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord[]
     */
    public static function searchByQuery($query, $languageInfo, $returnQuery = false)
    {
        $query = trim(htmlentities($query, ENT_QUOTES));
        
        if (strlen($query) < 1) {
            return [];
        }
        
        $parts = explode(" ", $query);
        
        $index = [];
        
        foreach ($parts as $word) {
            $q = self::find()->select(['id', 'content'])->where(['like', 'content', $word]);
            if (!empty($languageInfo)) {
                $q->andWhere(['language_info' => $languageInfo]);
            }
            $data = $q->asArray()->indexBy('id')->all();
            static::indexer($data, $index);
        }
        
        $ids = [];
        foreach ($index as $item) {
            $ids[] = $item['id'];
        }
        
        
        $activeQuery = self::find()->where(['in', 'id', $ids]);
        
        if ($returnQuery) {
            return $activeQuery;
        }
        
        $result = $activeQuery->all();
        
        $searchData = new SearchData();
        $searchData->attributes = [
            'query' => $query,
            'results' => count($result),
            'timestamp' => time(),
            'language' => Yii::$app->composition->language,
        ];
        $searchData->save();
        
        return $result;
    }
    
    /**
     * Search By query name.
     * 
     * @param unknown $query
     * @param unknown $languageInfo
     * @return \yii\db\ActiveRecord[]
     */
    public static function flatSearchByQuery($query, $languageInfo)
    {
        $query = trim(htmlentities($query, ENT_QUOTES));
        
        if (strlen($query) < 1) {
            return [];
        }
        
        $q = self::find()->where(['like', 'content', $query]);
        if (!empty($languageInfo)) {
            $q->andWhere(['language_info' => $languageInfo]);
        }
        $result = $q->all();
        
        $searchData = new SearchData();
        $searchData->attributes = [
            'query' => $query,
            'results' => count($result),
            'timestamp' => time(),
            'language' => Yii::$app->composition->language,
        ];
        $searchData->save();
        
        return $result;
    }

    /**
     * Generate preview from the search word and the corresponding cut amount.
     * 
     * @param string $word The word too lookup in the `$content` variable.
     * @param number $cutAmount The amount of words on the left and right side of the word.
     * @return mixed
     */
    public function preview($word, $cutAmount = 150)
    {
        return $this->highlight($word, $this->cut($word, html_entity_decode($this->content), $cutAmount));
    }

    /**
     * 
     * @param unknown $word
     * @param unknown $context
     * @param number $truncateAmount
     * @return string
     */
    public function cut($word, $context, $truncateAmount = 150)
    {
        $pos = strpos($context, $word);
        $originalContext = $context;
        // cut lef
        if ($pos > $truncateAmount) {
            $context = '...'.substr($context, (($pos - 1) - $truncateAmount));
        }
        // cut right
        if ((strlen($originalContext) - $pos) > $truncateAmount) {
            $context = substr($context, 0, -(strlen($originalContext) - ($pos + strlen($word) + 1) - $truncateAmount)).'...';
        }

        return $context;
    }

    /**
     * 
     * @param unknown $word
     * @param unknown $text
     * @param string $sheme
     * @return mixed
     */
    public function highlight($word, $text, $sheme = "<span style='background-color:#FFEBD1; color:black;'>%s</span>")
    {
        return preg_replace("/".preg_quote(htmlentities($word, ENT_QUOTES), '/')."/i", sprintf($sheme, $word), $text);
    }
    
    /**
     * @inheritdoc
     */
    public function genericSearchFields()
    {
        return ['url', 'content', 'title', 'language_info'];
    }

    /**
     * @inheritdoc
     */
    public static function ngRestApiEndpoint()
    {
        return 'api-crawler-index';
    }

    /**
     * @inheritdoc
     */
    public function ngRestAttributeTypes()
    {
        return [
            'url' => 'text',
            'title' => 'text',
            'language_info' => 'text',
            'url_found_on_page' => 'text',
            'content' => 'textarea',
            'last_update' => 'datetime',
            'added_to_index' => 'datetime',
        ];
    }

    /**
     * @inheritdoc
     */
    public function ngRestConfig($config)
    {
        $this->ngRestConfigDefine($config, 'list', ['title', 'url', 'language_info', 'last_update', 'added_to_index']);
        $this->ngRestConfigDefine($config, ['create', 'update'], ['url', 'title', 'language_info', 'url_found_on_page', 'content', 'last_update', 'added_to_index']);
        return $config;
    }
}
