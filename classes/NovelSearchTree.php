<?php
class TreeNode {
    public $novel;
    public $left;
    public $right;

    public function __construct($novel) {
        $this->novel = $novel;
        $this->left = null;
        $this->right = null;
    }
}

class NovelSearchTree {
    public $root;

    public function __construct() {
        $this->root = null;
    }

    //Inserts a novel into the tree alphabetically
    public function insert($novel) {
        $node = new TreeNode($novel);
        if ($this->root === null) {
            $this->root = $node;
        } else {
            $this->insertNode($this->root, $node);
        }
    }

    // Recursive helper to find the right spot
    private function insertNode(&$current, $newNode) {

        //Compare the titles
        if (strcasecmp($newNode->novel['title'], $current->novel['title']) < 0) {

            // Go Left
            if ($current->left === null) {
                $current->left = $newNode;
            } else {
                $this->insertNode($current->left, $newNode);
            }
        } else {

            // Go Right
            if ($current->right === null) {
                $current->right = $newNode;
            } else {
                $this->insertNode($current->right, $newNode);
            }
        }
    }

    //Searching logic, search the tree and find matches containing the same 
    public function search($query) {
        $results = [];
        $this->searchTree($this->root, $query, $results);
        return $results;
    }

    private function searchTree($node, $query, &$results) {
        if ($node !== null) {
            
            // 1. Check Left
            $this->searchTree($node->left, $query, $results);

            // 2. Check Current Node (Does title contain query?)
            if (stripos($node->novel['title'], $query) !== false) {
                $results[] = $node->novel;
            }

            // 3. Check Right
            $this->searchTree($node->right, $query, $results);
        }
    }
}
?>