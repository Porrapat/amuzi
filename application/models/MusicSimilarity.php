<?php

/**
 * MusicSimilarity Controllers interact with music_similarity db table through
 * this class.
 *
 * @package amuzi
 * @version 1.0
 * @copyright Copyright (C) 2010 Diogo Oliveira de Melo. All rights reserved.
 * @author Diogo Oliveira de Melo <dmelo87@gmail.com>
 * @license GPL version 3
 */
class MusicSimilarity extends DZend_Model
{
    /**
     * insert Inset an element.
     *
     * @param mixed $fArtistMusicTitleId First artist_music_title id.
     * @param mixed $sArtistMusicTitleId Second artist_music_title id.
     * @param mixed $similarity How similar this two rows are, from 1 to 10000.
     * @return void Returns the id of the row inserted in case of success, null 
     * otherwise.
     */
    public function insert(
        $fArtistMusicTitleId, $sArtistMusicTitleId, $similarity, $degree = 0
    )
    {
        list($f, $s) = $fArtistMusicTitleId < $sArtistMusicTitleId ?
            array($fArtistMusicTitleId, $sArtistMusicTitleId):
            array($sArtistMusicTitleId, $fArtistMusicTitleId);

        return $this->_musicSimilarityDb->insert(
            array('f_artist_music_title_id' => $f,
                's_artist_music_title_id' => $s,
                'similarity' => $similarity,
                'degree' => $degree
            )
        );
    }

    public function findByArtistMusicTitleIdAndDegree(
        $artistMusicTitleId, $degree
    )
    {
        $db = $this->_musicSimilarityDb->getAdapter();
        $sql = $db->quoteInto(
            '(f_artist_music_title_id = ?', $artistMusicTitleId
        ) .
        $db->quoteInto(' OR s_artist_music_title_id = ?', $artistMusicTitleId) .
        $db->quoteInto(') AND degree = ?', $degree);

        return $this->db->fetchAll($sql);
    }
}