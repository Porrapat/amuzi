<?php

/**
 * MusicSimilarity Controllers interact with music_similarity db table through
 * this class.
 *
 * @package amuzi
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2013  Diogo Oliveira de Melo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class MusicSimilarity extends DZend_Model
{
    public function packData(
        $fArtistMusicTitleId, $sArtistMusicTitleId, $similarity, $degree = 0
    )
    {
        return array(
            'f_artist_music_title_id' => $fArtistMusicTitleId,
            's_artist_music_title_id' => $sArtistMusicTitleId,
            'similarity' => $similarity,
            'degree' => $degree
        );
    }

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
        return $this->_musicSimilarityDb->insert(
            $this->packData(
                $fArtistMusicTitleId,
                $sArtistMusicTitleId,
                $similarity,
                $degree
            )
        );
    }

    public function findByArtistMusicTitleIdAndDegree(
        $artistMusicTitleId, $degree = 0
    )
    {
        $db = $this->_musicSimilarityDb->getAdapter();
        $sql = $db->quoteInto(
            '(f_artist_music_title_id = ?', $artistMusicTitleId
        ) .
        $db->quoteInto(' OR s_artist_music_title_id = ?)', $artistMusicTitleId);
        if (false !== $degree)
            $sql .= $db->quoteInto(' AND degree = ?', $degree);

        return $this->_musicSimilarityDb->fetchAll($sql, 'similarity desc');
    }

    public function findByArtistMusicTitleIdSetAndDegree(
        $artistMusicTitleIdSet, $degree = 0
    )
    {
        if (empty($artistMusicTitleIdSet)) {
            return array();
        } else {
            return $this->_musicSimilarityDb
                ->findByFArtistMusicTitleIdAndSArtistMusicTitleIdAndDegree(
                    $artistMusicTitleIdSet, $artistMusicTitleIdSet, $degree
                );
        }
    }

    public function getRandomArtistMusicTitleId()
    {
        return $this->_musicSimilarityDb->getRandomArtistMusicTitleId();
    }

    /**
     * _getSimilarityMatrix Calculate the similarity matrix for a given set of
     * elements
     *
     * @param mixed $list List of artist_music_title_id.
     * @return Returns an array with three elements: the similarity matrix
     * itself, the total number of elements and the quality of the similarity
     * matrix which is measured by N / M, where N is the number of non-zero
     * elements and M is the total number of elements on the matrix.
     */
    protected function _getSimilarityMatrix($list)
    {
        $this->_logger->debug('MS::_getSimilarityMatrix A ' . microtime(true));
        $rowSet = $this->findByArtistMusicTitleIdSetAndDegree($list, false);
        $this->_logger->debug('MS::_getSimilarityMatrix B ' . microtime(true));
        $matrix = array();
        foreach ($list as $a) {
            $matrix[$a] = array();
            foreach ($list as $b)
                $matrix[$a][$b] = 0;
        }
        $this->_logger->debug(
            'MS::_getSimilarityMatrix C ' . microtime(true) .
            ' -- ' . count($rowSet)
        );
        $count = 0;

        foreach ($rowSet as $row) {
            $count++;
            $a = $row->fArtistMusicTitleId;
            $b = $row->sArtistMusicTitleId;
            $matrix[$a][$b] = (int) $row->similarity;
        }

        // TODO: Extrapolate matrix using markov.

        // from last.fm semantic to incBoard semantic.
        foreach ($list as $a) {
            foreach ($list as $b) {
                if (0 !== $matrix[$a][$b] && 0 !== $matrix[$b][$a]) {
                    $matrix[$a][$b] = $matrix[$b][$a] = (int) ($matrix[$a][$b] + $matrix[$b][$a]) / 2;
                } elseif (0 !== $matrix[$a][$b]) {
                    $matrix[$b][$a] = $matrix[$a][$b];
                } elseif (0 !== $matrix[$b][$a]) {
                    $matrix[$a][$b] = $matrix[$b][$a];
                }
            }
        }

        $this->_logger->debug(
            'MusicSimilarity::_getSimilarityMatrix D ' .
            microtime(true) . "#" . $count
        );

        $total = count($list);
        $quality = $count / (($total * $total * 0.5) + 1);


        return array($matrix, $total, $quality);
    }

    protected function _applyListTranslation($similarityMatrix, $translationList)
    {
        $amtList = array();
        foreach ($similarityMatrix as $id => $cols) {
            $amtList[] = $id;
        }

        foreach ($translationList as $albumId => $amtIdSet) {
            // Fill the column
            foreach ($amtList as $i) {
                $sum = 0;
                foreach ($amtIdSet as $j) {
                    $sum += $similarityMatrix[$i][$j];
                }
                $similarityMatrix[$i][-$albumId] = (int) $sum / count($amtIdSet);
            }

            // Fill the row
            $similarityMatrix[-$albumId] = array();
            foreach ($amtList as $j) {
                $similarityMatrix[-$albumId][$j] = 0;
                $sum = 0;
                foreach ($amtIdSet as $i) {
                    $sum += $similarityMatrix[$i][$j];
                }
                $similarityMatrix[-$albumId][$j] = (int) $sum / count($amtIdSet);
            }

            $similarityMatrix[-$albumId][-$albumId] = 0;

            // erase replaced rows.
            foreach ($amtIdSet as $i) {
                unset($similarityMatrix[$i]);
            }

            // erase replace columns.
            foreach ($amtList as $i) {
                foreach ($amtIdSet as $j) {
                    unset($similarityMatrix[$i][$j]);
                }
            }

            // replace on amtList;
            foreach ($amtIdSet as $i) {
                if (($key = array_search($i, $amtList)) !== false) {
                    unset($amtList[$key]);
                }
            }
            $amtList[] = -$albumId;
        }

        return $similarityMatrix;
    }

    protected function _fetchObjList($idList)
    {
        $amtIdList = array();
        $albumIdList = array();

        foreach ($idList as $id) {
            if ($id < 0) {
                $albumIdList[] = -$id;
            } else {
                $amtIdList[] = $id;
            }
        }


        $amtList = empty($amtIdList) ? array() : $this->_artistMusicTitleModel->fetchAllArtistAndMusicTitle($amtIdList);
        foreach ($amtList as &$row) {
            $row['type'] = 'track';
        }

        $albumList = empty($albumIdList) ? array() : $this->_albumModel->fetchAllArtistAndAlbum($albumIdList);
        foreach ($albumList as &$row) {
            $row['type'] = 'album';
        }

        return array_merge($amtList, $albumList);
    }

    /**
     * Returns an array with two elements. The first is an array of similar
     * elements, each element containing artist and musicTitle. The second
     * element is the similarity matrix.
     */
    public function getSimilar(
        $artist, $musicTitle, $type, $artistMusicTitleIdList = array(), $mayUseSync = true
    )
    {
        $artistMusicTitleId = $this->_artistMusicTitleModel->insert(
            $artist, $musicTitle
        );
        $similarList = $this->_musicSimilarityDb->getSimilar(
            $artist, $musicTitle, $artistMusicTitleIdList
        );

        $ret = array();
        if (empty($similarList)) {
            $ret = $this->getSimilarSync(
                $artist, $musicTitle, $type, $artistMusicTitleIdList
            );
        } else {
            $completeIdList = array_merge(
                array($artistMusicTitleId),
                $similarList,
                $artistMusicTitleIdList
            );


            $similarityMatrixResponse = $this->_getSimilarityMatrix(
                $completeIdList
            );

            list($completeIdList, $translationList) = $this->_insertAlbumIds($completeIdList);

            $similarityMatrixResponse[0] = $this->_applyListTranslation($similarityMatrixResponse[0], $translationList);

            $this->_logger->debug(
                "MusicSimilarity::getSimilar local quality{ size: "
                . $similarityMatrixResponse[1] . ". non-zero: "
                . $similarityMatrixResponse[2]
            );

            if (
                ($similarityMatrixResponse[1] < 20 ||
                $similarityMatrixResponse[2] < 0.03) &&
                $mayUseSync
            ) {
                $ret = $this->getSimilarSync(
                    $artist, $musicTitle, $type, $artistMusicTitleIdList
                );
            } else {
                $objList = $this->_fetchObjList($completeIdList);

                // If local information is used, then there must be a task to
                // refresh current data.
                $this->_logger->debug("MusicSimilarity::getSimilar $artist $musicTitle " . print_r($type, true));
                $this->_taskRequestModel->addTask(
                    'SearchSimilar', $artist, $musicTitle, $type
                );

                $ret = array(
                    $objList,
                    $similarityMatrixResponse[0]
                );
            }
        }

        return $ret;
    }

    /**
     * getSimilarSync The sync version of getSimilar. It just request lastfm,
     * record the results on database and call getSimilar.
     */
    public function getSimilarSync(
        $artist, $musicTitle, $type, $artistMusicTitleIdList = array()
    )
    {
        $rowSet = $this->_lastfmModel->getSimilar($artist, $musicTitle);
        $artistMusicTitleId = $this->_artistMusicTitleModel->insert(
            $artist, $musicTitle
        );
        $artistMusicTitleIdList[] = $artistMusicTitleId;
        $list = array(array(
            'artist' => $artist,
            'musicTitle' => $musicTitle,
            'artistMusicTitleId' => $artistMusicTitleId
        ));

        foreach ($rowSet as $row) {
            $sArtistMusicTitleId = $this->_artistMusicTitleModel->insert(
                $row->artist, $row->musicTitle
            );

            if (null !== $sArtistMusicTitleId) {
                $this->insert(
                    $artistMusicTitleId,
                    $sArtistMusicTitleId,
                    $row->similarity
                );
            }
        }

        return $this->getSimilar($artist, $musicTitle, $type, $artistMusicTitleIdList, false);
    }

    protected function _insertAlbumIds($artistMusicTitleIdList)
    {
        $translationList = array();
        $albumAMTRowSet = $this->_albumHasArtistMusicTitleDb->fetchAllByArtistMusicTitleIdGrouped($artistMusicTitleIdList);

        $albumIdCount = array();
        foreach ($albumAMTRowSet as $row) {
            if (!array_key_exists($row->albumId, $albumIdCount)) {
                $albumIdCount[$row->albumId] = array();
            }
            $albumIdCount[$row->albumId][] = $row->artistMusicTitleId;
        }
        $freq = array();
        for ($i = 0; $i <= count($artistMusicTitleIdList); $i++) {
            $freq[$i] = array();
        }

        foreach ($albumIdCount as $albumId => $amtList) {
            $freq[count($amtList)][] = $albumId;
        }

        $maxReplacements = (int) count($artistMusicTitleIdList) / 2;

        for ($i = count($artistMusicTitleIdList); $i >= 0; $i--) {
            if ($maxReplacements <= 0) {
                break;
            }

            foreach ($freq[$i] as $albumId) {
                $translationList[$albumId] = array();
                foreach ($albumIdCount[$albumId] as $amtId) {
                    $translationList[$albumId][] = $amtId;
                    if (($key = array_search($amtId, $artistMusicTitleIdList)) !== false) {
                        unset($artistMusicTitleIdList[$key]);
                        $maxReplacements--;
                    }
                }
                $artistMusicTitleIdList[] = -$albumId;
                if ($maxReplacements <= 0) {
                    break;
                }
            }
        }

        return array($artistMusicTitleIdList, $translationList);
    }
}
