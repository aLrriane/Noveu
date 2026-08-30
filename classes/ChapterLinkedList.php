<?php
// holds chapters data
class ChapterNode {
    public $data;       //id, title, content)
    public $next;       //next chap
    public $prev;       //previous chap

    public function __construct($data) {
        $this->data = $data;
        $this->next = null;
        $this->prev = null;
    }
}

//Linked lIst
class ChapterLinkedList {
    public $head;
    public $tail;

    public function __construct() {
        $this->head = null;
        $this->tail = null;
    }

    // Adding a chapter at the end of the list
    public function addChapter($chapterData) {
        $newNode = new ChapterNode($chapterData);

        if ($this->head === null) {
            $this->head = $newNode;
            $this->tail = $newNode;
        } else {

            $this->tail->next = $newNode;
            $newNode->prev = $this->tail;
            $this->tail = $newNode; // Update tail
        }
    }

    //searching for chapter by ID and return its Node for gettingg prev/next)
    public function getChapterNode($chapterId) {
        $current = $this->head;
        while ($current !== null) {
            if ($current->data['id'] == $chapterId) {
                return $current;
            }
            $current = $current->next;
        }
        return null;
    }
}
?>