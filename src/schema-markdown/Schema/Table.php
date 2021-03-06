<?php

namespace SchemaMarkdown\Schema;

use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Support\Fluent;

class Table
{
    /**
     * @var \SchemaMarkdown\Schema\Database
     */
    protected $database;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var \SchemaMarkdown\Schema\Index[]
     */
    protected $indices = [];

    /**
     * @param \SchemaMarkdown\Schema\Database $database
     * @param string $name
     */
    public function __construct(Database $database, string $name)
    {
        $this->database = $database;
        $this->setTableName($name);
    }

    /**
     * @return \SchemaMarkdown\Schema\Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->name;
    }

    /**
     * @return void
     */
    public function setTableName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return \SchemaMarkdown\Schema\Column|null
     */
    public function getColumn($column)
    {
        return $this->columns[$column] ?? null;
    }

    /**
     * @return \SchemaMarkdown\Schema\Index[]
     */
    public function getIndices()
    {
        return $this->indices;
    }

    /**
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @return void
     */
    public function applyBlueprint(Blueprint $blueprint)
    {
        foreach ($blueprint->getCommands() as $command) {
            $this->runCommand($command, $blueprint);
        }
    }

    /**
     * @param \Illuminate\Support\Fluent $command
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @return void
     */
    protected function runCommand(Fluent $command, Blueprint $blueprint)
    {
        $method = 'command_'.$command['name'];
        if (method_exists($this, $method)) {
            $this->{$method}($command, $blueprint);
        }
    }

    protected function command_create(Fluent $command, Blueprint $blueprint)
    {
        $this->database->setTable($this->getTableName(), $this);
        foreach ($blueprint->getColumns() as $column) {
            $this->columns[$column->get('name')] = new Column($this, $column);
        }
    }

    protected function command_drop(Fluent $command, Blueprint $blueprint)
    {
        $this->database->dropTable($this->name);
    }

    protected function command_dropIfExists(Fluent $command, Blueprint $blueprint)
    {
        $this->database->dropTable($this->name);
    }

    protected function command_dropColumn(Fluent $command, Blueprint $blueprint)
    {
        $columns = $command['columns'];
        foreach ($columns as $column) {
            unset($this->columns[$column]);
        }
    }

    protected function command_renameColumn(Fluent $command, Blueprint $blueprint)
    {
        [$from, $to] = [$command['from'], $command['to']];
        $column = $this->columns[$from];
        unset($this->columns[$from]);
        $column->updateByCommand($command);
        $this->columns[$to] = $column;
    }

    protected function command_dropPrimary(Fluent $command, Blueprint $blueprint)
    {
        $this->applyDropIndex($command);
    }

    protected function command_dropUnique(Fluent $command, Blueprint $blueprint)
    {
        $this->applyDropIndex($command);
    }

    protected function command_dropIndex(Fluent $command, Blueprint $blueprint)
    {
        $this->applyDropIndex($command);
    }

    protected function command_dropSpatialIndex(Fluent $command, Blueprint $blueprint)
    {
        $this->applyDropIndex($command);
    }

    protected function command_dropForeign(Fluent $command, Blueprint $blueprint)
    {
        $this->applyDropIndex($command);
    }

    protected function command_renameIndex(Fluent $command, Blueprint $blueprint)
    {
        foreach ($this->indices as $index) {
            if ($index->getName() != $command['from']) {
                continue;
            }
            $index->updateByCommand($command);
            $this->updateIndexRelatedColumnsByCommand($index, $command);
        }
    }

    protected function command_rename(Fluent $command, Blueprint $blueprint)
    {
        [$from, $to] = [$command['from'], $command['to']];
        $this->database->dropTable($from);
        $this->name = $to;
        $this->database->setTable($to, $this);
    }

    protected function command_primary(Fluent $command, Blueprint $blueprint)
    {
        $this->applyIndex($command);
    }

    protected function command_unique(Fluent $command, Blueprint $blueprint)
    {
        $this->applyIndex($command);
    }

    protected function command_index(Fluent $command, Blueprint $blueprint)
    {
        $this->applyIndex($command);
    }

    protected function command_spatialIndex(Fluent $command, Blueprint $blueprint)
    {
        $this->applyIndex($command);
    }

    protected function command_foreign(Fluent $command, Blueprint $blueprint)
    {
        $this->applyIndex($command);
    }

    protected function command_add(Fluent $command, Blueprint $blueprint)
    {
        foreach ($blueprint->getAddedColumns() as $column) {
            $this->columns[$column->get('name')] = new Column($this, $column);
        }
    }

    protected function command_change(Fluent $command, Blueprint $blueprint)
    {
        foreach ($blueprint->getChangedColumns() as $column) {
            $this->columns[$column->get('name')]->update($column);
        }
    }

    protected function applyIndex(Fluent $command)
    {
        array_push($this->indices, $index = new Index($command));
        $this->updateIndexRelatedColumnsByCommand($index, $command);
    }

    protected function applyDropIndex(Fluent $command)
    {
        foreach ($this->indices as $index) {
            if ($index->getName() != $command['index']) {
                continue;
            }
            $this->updateIndexRelatedColumnsByCommand($index, $command);
        }
        $this->indices = array_filter($this->indices, function ($entry) use ($command) {
            return $entry->getName() != $command['index'];
        });
    }

    protected function updateIndexRelatedColumnsByCommand(Index $index, Fluent $command)
    {
        foreach ($index->getColumns() as $column) {
            if ($column_definition = $this->getColumn($column)) {
                $column_definition->updateByCommand($command);
            }
        }
    }
}
