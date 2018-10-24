<?php

namespace app;

class NodesRequest
{
    private $db;
    private $path;

    public function __construct(DB $db, $path=null)
    {
        $this->db = $db;
        if (null !== $path) {
            $this->path = trim(urldecode($path), '/');
        }
    }

    public function render(): void
    {
        if ((null === $node = $this->db->getNode($this->path)) || !isset($node['path'])) {
            http_response_code(404);
        }

        $sequence = DB::preparePath($this->path);
        $sequence_length = \count($sequence);
        $aux =& $node['path'];
        $nested_level = 0;
        $last_path = ['level' => 0, 'path' => 0];
        foreach ($sequence as $iterator => $path_index) {
            if ('nested' !== $path_index) {
                for ($i = 0; $i <= $path_index; $i++) {
                    $path = array_fill(0, $nested_level, 0);
                    $path[$nested_level] = (int)$i;
                    $path[$last_path['level']] = $last_path['path'];

                    $aux[$i] = [
                        'name' => 'tmp',
                        'path' => $path,
                    ];
                    if ($sequence_length === $iterator + 1 && $i === (int)$path_index && isset($node['name'])) {
                        $aux[$i]['name'] = $node['name'];
                    }
                }
                $last_path = ['level' => $nested_level, 'path' => (int)$path_index];
                $nested_level++;
            }
            unset($node['path'][$iterator + 1]);
            $aux =& $aux[$path_index];
        }

        echo json_encode($node['path']);
    }

    public function merge(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $cache =& $data['cache'];
        $node =& $data['node'];
        $state = $this->merge_tree($cache, $node);

        echo json_encode($state);
    }

    public function rename(): void
    {
        $sequence = DB::preparePath($this->path);

        $data = json_decode(file_get_contents('php://input'), true);

        $this->db->goto($this->path, function (&$state) use ($data) {
            $state['name'] = $data['name'];
        });

        echo json_encode($sequence);
    }

    private function merge_tree(&$cache, $node)
    {
        foreach ($node as $index => $value) {
            if ($cache[$index] !== $value) {
                if (\is_array($value)) {
                    $this->merge_tree($cache[$index], $value);
                } else if ($value !== 'tmp' || $cache === null) {
                    $cache[$index] = $value;
                }
            }
        }

        return $cache;
    }
}