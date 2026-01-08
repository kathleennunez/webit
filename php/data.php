<?php
function data_path(string $file): string {
  return DATA_DIR . '/' . $file;
}

function read_json(string $file): array {
  $path = data_path($file);
  if (!file_exists($path)) {
    return [];
  }
  $contents = file_get_contents($path);
  $decoded = json_decode($contents, true);
  return is_array($decoded) ? $decoded : [];
}

function write_json(string $file, array $data): void {
  $path = data_path($file);
  $json = json_encode($data, JSON_PRETTY_PRINT);
  $fp = fopen($path, 'c+');
  if ($fp === false) {
    throw new RuntimeException('Unable to open data file');
  }
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  fwrite($fp, $json);
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
}

function append_json(string $file, array $item): void {
  $data = read_json($file);
  $data[] = $item;
  write_json($file, $data);
}
