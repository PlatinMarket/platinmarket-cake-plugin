<?php

App::uses('File', 'Utility');
App::uses('Hash', 'Utility');
App::uses('ReformApiException', 'Platinmarket.Lib');

class ReformApiData
{

  // Db File
  private $file = null;

  // Initialize
  public function __construct($dbFile)
  {
    try
    {
      $this->file = new File($dbFile, true, 0775);
    }
    catch (Exception $e)
    {
      throw new ReformApiException($e->getMessage());
    }
  }

  // Terminate
  public function __destruct()
  {
    if (!is_null($this->file)) unset($this->file);
  }

  // Data cache
  private $data = null;

  // Get data array
  private function __readFile()
  {
    if (!is_null($this->data)) return $this->data;
    if (($data = $this->file->read()) === false) throw new ReformApiException('Error occured while reading file');
    $this->data = unserialize($data);
    if (empty($this->data)) $this->data = array();
    return $this->data;
  }

  // Write data array
  private function __writeFile($data)
  {
    $this->data = $data;
    if ($this->file->write(serialize($this->data), "w", true) === false) throw new ReformApiException('Error occured while writing file');
    return true;
  }

  // read
  public function read($path = null)
  {
    if (empty($path)) return $this->__readFile();
    return Hash::get($this->__readFile(), $path);
  }

  // extract
  public function extract($path)
  {
    return Hash::extract($this->__readFile(), $path);
  }

  // write
  public function write($path, $values = null)
  {
    $this->__writeFile(Hash::insert($this->__readFile(), $path, $values));
  }

  // remove
  public function remove($path)
  {
    $this->__writeFile(Hash::remove($this->__readFile(), $path));
  }
}
