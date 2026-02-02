<?php

namespace Drupal\w2w2l;

interface ClientInterface {

  public function create($sobject, $type);

  public function retrieve($id, $sobject_type, $fields = []);

  public function update($id, $sobject_type, $sobject);

  public function attach(string $id, string $fileName, \SplFileInfo $file);

  public function getAttachments(string $linkedEntityId): array;

}
