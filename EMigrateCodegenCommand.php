<?php

Yii::import('system.cli.commands.MigrateCommand');

class EMigrateCodegenCommand extends MigrateCommand
{
	public $templatePartiallyImplementedFile;
	public $createTableOptions = 'ENGINE=InnoDB CHARSET=utf8';
	
	public function actionCreate($args) 
	{
		if(isset($args[0])) 
		{
			$name=$args[0];
		}
		else
		{
			$this->usageError('Please provide the name of the new migration.');		
		}
		
		$data = $this->parseMigrationName($name);
		$name = 'm'.gmdate('ymd_His').'_'.$name;
		$file = $this->migrationPath.DIRECTORY_SEPARATOR.$name.'.php';
		
		//it's standart command
		if (! $data) 
		{
			$content=strtr($this->getTemplate(), array('{ClassName}'=>$name));
		}
		//it's partially implemented command
		else
		{
			$this->addCommandLineParams($data, $args);
			$template = $this->getPartiallyImplementedTemplate();
			$content = strtr($template, array(
				'{ClassName}' => $name,
				'{CodeForUp}' => $this->getCodeForUp($data),
				'{CodeForDown}' => $this->getCodeForDown($data)
			));
		}
		
		if($this->confirm("Create new migration '$file'?"))
		{
			file_put_contents($file, $content);
			echo "New migration created successfully.\n";
		}		
	}
	
	protected function parseMigrationName($name)
	{
		//it's CREATE TABLE or DROP TABLE
		preg_match('/^(create|drop)_table_(.+)$/i', $name, $matches);
		if ($matches) 
		{
			return array(
				'command' => $matches[1] == 'create' ? 'createTable' : 'dropTable',
				'tableName' => $matches[2],
			);
		}

		//it's ADD COLUMN or DROP COLUMN
		preg_match('/^(add|drop)_column_(.+)_in_(.+)$/i', $name, $matches);
		if ($matches) 
		{
			return array(
				'command' => $matches[1] == 'add' ? 'addColumn' : 'dropColumn',
				'columnName' => $matches[2],
				'tableName' => $matches[3],
			);
		}
		
		return array();
	}
	
	protected function addCommandLineParams(&$data, $args)
	{
		if ($data['command'] == 'createTable') {
			$data['options'] = isset($args[1]) ? $args[1] : $this->createTableOptions;
		}
	}
	
	protected function getPartiallyImplementedTemplate()
	{
		if($this->templatePartiallyImplementedFile!==null)
		{
			return file_get_contents(Yii::getPathOfAlias($this->templateFile).'.php');
		}
		else
		{
			return <<<EOD
<?php
			
class {ClassName} extends CDbMigration
{
	public function up()
	{
		{CodeForUp}
	}

	public function down()
	{
		{CodeForDown}
	}

	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
		
	}

	public function safeDown()
	{
		
	}
	*/
}			
EOD;
			
		}
	}
	
	protected function getCodeForUp($data)
	{
		switch ($data['command']) {
			case 'createTable':
				return $this->getCodeForCreateTable($data);
				break;
			case 'dropTable':
				return $this->getCodeForDropTable($data);
				break;
			case 'addColumn':
				return $this->getCodeForAddColumn($data);
				break;
			case 'dropColumn':
				return $this->getCodeForDropColumn($data);
				break;
		}
		return '';
	}
	
	protected function getCodeForDown($data)
	{
		switch ($data['command']) {
			case 'createTable':
				return $this->getCodeForDropTable($data);
				break;
			case 'dropTable':
				return $this->getCodeForCreateTable($data);
				break;
			case 'addColumn':
				return $this->getCodeForDropColumn($data);
				break;
			case 'dropColumn':
				return $this->getCodeForAddColumn($data);
				break;
		}
		return '';
	}
	
	protected function getCodeForCreateTable($data)
	{
		$createTableTemplate = 
		'$this->createTable(\'{tableName}\', array(
			
		), {options});';
		
		return strtr($createTableTemplate, array(
			'{tableName}' => $data['tableName'],
			'{options}' => $data['options'] == 'null' 
				? $data['options']
				: "'".$data['options']."'"
		));
	}
	
	protected function getCodeForDropTable($data)
	{
		$dropTableTemplate = '$this->dropTable(\'{tableName}\');';
		return strtr($dropTableTemplate, array(
			'{tableName}' => $data['tableName']
		));
	}
	
	protected function getCodeForAddColumn($data)
	{
		$addColumnTemplate = '$this->addColumn(\'{tableName}\', \'{columnName}\', \'\');';
		return strtr($addColumnTemplate, array(
			'{tableName}' => $data['tableName'],
			'{columnName}' => $data['columnName'],
		));
	}
	
	protected function getCodeForDropColumn($data)
	{
		$dropColumnTemplate = '$this->dropColumn(\'{tableName}\', \'{columnName}\');';
		return strtr($dropColumnTemplate, array(
			'{tableName}' => $data['tableName'],
			'{columnName}' => $data['columnName'],			
		));		
	}
}