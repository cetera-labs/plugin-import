Ext.define('Plugin.import.Panel', {

    extend:'Ext.TabPanel',
	
	border: false,
	padding: 10,
    bodyCls: 'x-window-body-default',        
    cls: 'x-window-body-default',
	style: 'border: none',
	
	requires: [
		'Cetera.field.File',
		'Cetera.field.MaterialType',
		'Cetera.field.Folder'
	],
	
	defaults: {
		padding: 10,
		bodyCls: 'x-window-body-default',        
		cls: 'x-window-body-default',	
		border: false,	
		buttonAlign: 'left'
	},
	
	setupValues: false,
	fieldValues: false,
	
	stopImport: 0,
	
    initComponent: function(){
						
		this.catalog = Ext.create('Cetera.field.Folder',{
			fieldLabel: _('Раздел'),
			name: 'catalog',
			allowBlank: false,
			disabled: 1
		});	
				
        this.fieldsStore = Ext.create('Ext.data.JsonStore',{
            autoDestroy: false,
            fields: ['id','name'],
            proxy: {
                type: 'ajax',
				url: '/plugins/import/data_fields.php',
                extraParams: {
                    'type_id': 0
                },
                reader: {
                    type: 'json',
					root: 'rows'
				}				
            }
        });	

		this.templatesStore = Ext.create('Ext.data.Store',{
			autoDestroy: false,
			autoLoad: true,
			autoSync: true,		
			fields: ['id','name','setup','fields'],
			proxy: {
				type: 'ajax',
				api: {
					read    : '/plugins/import/data_templates.php',
					destroy : '/plugins/import/data_templates.php?action=destroy'
				},	
				reader: {
					type: 'json',
					root: 'rows'
				}
			}
		});
		
		this.dataSourceStore = Ext.create('Ext.data.JsonStore',{
			autoDestroy: false,
			autoLoad: true,
			fields: ['id','name'],
			proxy: {
				type: 'ajax',
				url: '/plugins/import/data_sources.php',			
				reader: {
					type: 'json',
					root: 'rows'
				}				
			}
		});		
        		
        Ext.apply(this, {
			
            items: [
				{
					title: _('Настройки'),	
					itemId: 'setup',
					xtype: 'form',
					overflowY: 'auto',
					defaults: {
						anchor: '100%',
						maxWidth: 600
					},		
					items: [
						Ext.create('Ext.grid.Panel', {
							title: _('Сохраненные шаблоны'),
							store: this.templatesStore,
							hideHeaders: true,
							height: 150,
							margin: '0 0 5 0',
							columns: [
								{ dataIndex: 'id', width:50 },
								{ dataIndex: 'name', flex: 1 }
							],
							tbar: [
								{
									xtype: 'button', 
									iconCls:'icon-reload',
									scope: this,
									handler: function(btn) {
										this.templatesStore.load();
									}
								},							
								{
									xtype: 'button', 
									text: _('Использовать'),
									iconCls:'icon-lamp_glow',
									scope: this,
									handler: function(btn) {
										var sm  = btn.up('grid').getSelectionModel()
										var sel = sm.getSelection();
										if (sel.length)
										{
											this.setupValues = sel[0].get('setup');
											this.fieldValues = sel[0].get('fields');
											this.setupValues.materials_type = parseInt(this.setupValues.materials_type);											
											this.catalog.enable();
											this.setDataSource(this.setupValues.data_source, this.setupValues );
											sm.deselectAll();
											
											this.activeTemplate = sel[0].get('name');
											this.child('#setup').queryById('save').setText(_('Сохранить')+' "'+this.activeTemplate+'"').enable();
											this.child('#fields').queryById('save').setText(_('Сохранить')+' "'+this.activeTemplate+'"').enable();
										}
									}
								},
								{ 
									xtype: 'button', 
									text: _('Удалить'), 
									iconCls:'icon-delete',
									scope: this,
									handler: function(btn) {
										var sm  = btn.up('grid').getSelectionModel()
										var sel = sm.getSelection();
										if (sel.length)
										{
											Ext.MessageBox.confirm(_('Удалить шаблон'), Config.Lang.r_u_sure, function(btn) {
												if (btn == 'yes') {
													this.templatesStore.remove(sel);
												}
											}, this);											
										}										
									}									
								}
							]							
						}),				
						{
							xtype:'fieldset',
							title: _('Формат данных'),
							layout:'anchor',
							defaults: {
								anchor: '100%',
								hideEmptyLabel: false
							},
							items :[{
								xtype: 'materialtypefield',
								fieldLabel: _('Тип материалов'),
								allowBlank: false,
								name: 'materials_type',
								linkable: 1,
								listeners: {
									select: {
										fn: function(combo){
											this.catalog.enable();
											this.catalog.setOnly( combo.getValue() );
										},
										scope: this
									}
								}
							},
							this.catalog,	
							{
								xtype: 'combobox',
								fieldLabel: _('Источник данных'),
								name: 'data_source',
								displayField: 'name',
								valueField: 'id',
								allowBlank: false,
								editable: false,
								store: this.dataSourceStore,
								listeners: {
									select: {
										fn: function(combo){
											this.setDataSource(combo.getValue(),false);
										},
										scope: this
									}
								}							
							}]
						},{
							xtype:'fieldset',
							itemId: 'datasource_fieldset',
							title: _('Настройки источника данных'),
							hidden: true,
							layout:'anchor',
							defaults: {
								anchor: '100%',
								hideEmptyLabel: false
							},							
						},{
							xtype:'fieldset',
							title: _('Настройки импорта'),
							layout:'anchor',
							defaults: {
								anchor: '100%',
								labelWidth: 200,
								hideEmptyLabel: false
							},
							items :[
								{
									xtype: 'textfield',
									fieldLabel: _('Уникальное поле материала'),
									name: 'unique_field',
									value: 'alias',
									allowBlank: false
								}, 
								{
									xtype: 'radio',
									fieldLabel: _('Материалы, которых нет в источнике'),
									boxLabel: _('деактивировать'),
									name: 'missing',
									inputValue: 'unpublish'
								}, {
									xtype: 'radio',
									boxLabel: _('удалить'),
									name: 'missing',
									inputValue: 'delete'
								}, {
									xtype: 'radio',
									boxLabel: _('не трогать'),
									name: 'missing',
									checked: 1,
									inputValue: 'skip'
								},{
									xtype: 'checkbox',
									fieldLabel: _('Не создавать новые материалы'),
									name: 'no_new_materials',
									inputValue: 1
								}
							]
						}
					],					
					buttons: [{
						text: _('Сохранить шаблон'),
						itemId: 'save',
						scope: this,
						width: 150,
						disabled: true,
						handler: this.saveActiveTemplateStep1
					},{
                        text: _('Сохранить как новый шаблон ...'),
                        scope: this,
                        width: 150,
                        handler: this.saveTemplateAs
                    },{
						text: _('Дальше >'),
						itemId: 'step1',
						xtype: 'button',
						scope: this,
						width: 150,
						handler: this.step1Click
					}]
				},{
					title: _('Поля'),
					itemId: 'fields',					
					disabled: true,					
					xtype: 'form',
					bodyPadding: 5,
					overflowY: 'auto',
					defaults: {
						anchor: '100%',
						maxWidth: 800,
					},						
					
					buttons: [
						{
							text: _('< Назад'),
							scope: this,
							width: 150,
							handler: function() {
								this.fieldValues = this.child('#fields').getForm().getValues();
								//console.log(this.fieldValues);
								this.child('#setup').enable();
								this.setActiveTab('setup');
								this.child('#fields').disable();									
							}
						},{
							text: _('Сохранить шаблон'),
							itemId: 'save',
							scope: this,
							width: 150,
							disabled: true,
							handler: this.saveActiveTemplateStep2
						},{
							text: _('Сохранить как новый шаблон ...'),
							scope: this,
							width: 150,
							handler: this.saveTemplateAs
						},{
							text: _('Начать импорт >>'),
							scope: this,
							width: 150,
							handler: this.startImport
						}						
					]
				},{
					title: _('Результат'),
					itemId: 'result',
					disabled: true,
					buttons: [
						{
							text: '< Назад',
							scope: this,
							itemId: 'prev',
							width: 150,
							handler: function() {
								this.child('#fields').enable();
								this.setActiveTab('fields');
								this.child('#result').disable();	
								this.stopImport = 1;
							}
						}						
					]					
				}
			]					
                        
        });              
        
        this.callParent(arguments);

    },
	
	setDataSource: function(value, values) {
		var fs = this.child('#setup').queryById('datasource_fieldset');
		
		Ext.Ajax.request({
			url: '/plugins/import/action_datasource_setup.php?data_source='+value,
			scope: this,
			success: function(resp) {
				var obj = Ext.decode(resp.responseText);
				if (obj.success)
				{
					fs.show();
					fs.removeAll();
					Ext.Array.each(obj.fields, function(field, index) {	
						fs.add(Ext.widget(field));															
					}, this);
					
					if (obj.fields.length > 0)
						fs.show();	
						else fs.hide();

					if (values)
						this.child('#setup').getForm().setValues( values );					
				}
			}
		});	
			
	},
	
	startImport: function(btn) {
		this.fieldValues = this.child('#fields').getForm().getValues();
		this.child('#result').enable();
		this.setActiveTab('result');
		this.child('#fields').disable();

		this.child('#result').update(_('Подождите ...'));
		this.stopImport = 0;
		//this.child('#result').queryById('prev').disable();
		this.doImport({counter: 0});
	},
	
	doImport: function(data) {
		if (this.stopImport) return;
        Ext.Ajax.request({
            url: '/plugins/import/action_import.php',
			params: Ext.Object.merge(
				data,
				this.setupValues,
				this.fieldValues
			),
            scope: this,
            success: function(resp) {
                var obj = Ext.decode(resp.responseText);
				this.child('#result').update( obj.message );
				if (obj.counter)
				{
					this.doImport( obj );
				}
				else
				{					
					this.child('#result').queryById('prev').enable();
				}
            },
			failure: function(resp) {
				var obj = Ext.decode(resp.responseText);
				this.child('#result').update( '<div style="color:red"><b>'+_('Ошибка')+':</b> '+obj.message+'</div>' );
				this.child('#result').queryById('prev').enable();
			}
        });			
	},

	step1Click: function(btn) {
			
		var f = btn.up('form').getForm();
		if (!f.isValid()) return;
		this.setupValues = f.getValues();

        Ext.Ajax.request({
            url: '/plugins/import/action_datasource_fields.php',
            params: this.setupValues,
            scope: this,
            success: function(resp) {
                var obj = Ext.decode(resp.responseText);
				if (obj.success)
				{
					this.fieldsStore.proxy.extraParams.type_id = this.setupValues.materials_type;		
					this.child('#fields').enable();
					this.setActiveTab('fields');
					this.child('#setup').disable();					
					this.setFields(obj.fields);
				}
            }
        });		
		
	},
	
	setFields: function( fields ) {
		var fieldsPanel = this.child('#fields');
		
		fieldsPanel.items.each(function(item) {
			item.removeAll(true);
		});
		fieldsPanel.removeAll();
		
		//console.log(this.fieldValues);
		
		Ext.Object.each(fields, function(index, name)
		{

			fieldsPanel.add(
				Ext.widget('fieldcontainer',{
					labelWidth: 300,
					fieldLabel: name,					
					layout: 'hbox',
					items: [{
						xtype: 'combobox',
						store: this.fieldsStore,
						valueField:'id',
						displayField:'name',
						editable: false,
						name: 'fields_'+index,
						value: '',
						flex: 2
					},{
						xtype: 'label',
						text: _('фильтр'),
						margin: '0 10'
					},{
						xtype: 'textfield',
						name: 'filter_'+index,
						value: '',
						flex: 1
					}]
				})
			);
					
		}, this);
		
		fieldsPanel.add(
			Ext.widget('panel',{
				html: _('Если установлен фильтр, то импортируются элементы, значение поля которых совпадает с фильтром. Допускаются регулярные выражения.')
			})
		);

		if (this.fieldValues) 
		{
			this.fieldsStore.load({
				scope: this,
				callback: function(records, operation, success) {
					fieldsPanel.getForm().setValues( this.fieldValues );
				}
			});			
		}
	},
	
	saveActiveTemplateStep1: function(btn) {
		var f = btn.up('form').getForm();
		if (!f.isValid()) return;
		this.setupValues = f.getValues();		
		this.saveTemplate(this.activeTemplate);
	},	

	saveActiveTemplateStep2: function() {
		this.fieldValues = this.child('#fields').getForm().getValues();
		this.saveTemplate(this.activeTemplate);
	},		
	
	saveTemplateAs: function(btn) {
		var f = btn.up('form').getForm();
		if (!f.isValid()) return;        
		Ext.Msg.prompt(_('Сохранить шаблон'), _('Введите имя шаблона'), function(btn, text){
			if (btn == 'ok' && text)
			{
                this.setupValues = f.getValues();	
				this.fieldValues = this.child('#fields').getForm().getValues();
				this.saveTemplate(text);
			}
		}, this);		
	},
	
	saveTemplate: function(name) {
		if (!name) return;
		Ext.Ajax.request({
			url: '/plugins/import/action_save_template.php?import_type=csv&name='+name,
			scope: this,
			params: Ext.Object.merge(
				this.setupValues,
				this.fieldValues
			),
			success: function(){
				this.templatesStore.load();
                
                this.activeTemplate = name;
                this.child('#setup').queryById('save').setText(_('Сохранить')+' "'+this.activeTemplate+'"').enable();
                this.child('#fields').queryById('save').setText(_('Сохранить')+' "'+this.activeTemplate+'"').enable();                
                
			}
		});
	}	
                
});