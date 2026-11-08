<?php

require_once dirname(__FILE__) . '/PrestaShopStub.php';

require_once dirname(dirname(__DIR__)) . '/statssearch.php';

class StatssearchGraphProbe extends statssearch
{
    public function load($layers)
    {
        $this->_legend = array();
        $this->_values = array();
        $this->_titles = array('main' => null, 'x' => null, 'y' => null);
        $this->getData($layers);

        return array(
            'legend' => $this->_legend,
            'values' => $this->_values,
            'title' => $this->_titles['main'],
        );
    }
}
