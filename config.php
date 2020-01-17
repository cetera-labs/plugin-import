<?php
// Подключаем каталог с переводами модуля
$t = $this->getTranslator();
$t->addTranslation(__DIR__.'/lang');

\Import\DataSourceIterator::add(array(
	'id' => '\Import\DataSource\CSV',
	'name' => $t->_('Файл CSV')
));

\Import\DataSourceIterator::add(array(
	'id' => '\Import\DataSource\XML',
	'name' => $t->_('Файл XML')
));

\Import\DataSourceIterator::add(array(
	'id' => '\Import\DataSource\CommerceML2',
	'name' => 'CommerceML 2.0'
));

\Import\DataSourceIterator::add(array(
	'id' => '\Import\DataSource\MoySklad',
	'name' => $t->_('Мой склад (moysklad.ru)')
));

if ( $this->getBo() )
{
    $this->getBo()->addModule(array(
		'id'       => 'import',
		'position' => MENU_SITE,
        'name' 	   => $t->_('Импорт'),
        'icon'     => '/cms/plugins/import/images/import.gif',
		'class'    => 'Plugin.import.Panel',
    ));

}