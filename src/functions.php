<?php

namespace Dtmcli {

    /**
     * @param string $dtmUrl
     * @return string
     * @throws \Exception
     */
    function genGid(string $dtmUrl): string
    {
        $client   = new \GuzzleHttp\Client();
        $response = $client->get($dtmUrl . '/newGid');
        checkStatus($response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        return $data['gid'];
    }

    /**
     * @param int $status
     * @throws \Exception
     */
    function checkStatus(int $status): void
    {
        if ($status !== 200) {
            throw new \Exception("bad http response status: {$status}");
        }
    }

    /**
     * @param string $str
     * @param string $errorMsg error message default ''
     * @throws \Exception
     */
    function checkFailure(string $str, string $errorMsg = ''): void
    {
        $resObj = json_decode($str, true);
        if (stripos($str, 'FAILURE') !== false) {
            if (!empty($resObj)) {
                $errorMsg = isset($resObj['msg']) ? $resObj['msg'] : $errorMsg;
            }
            throw new \Exception($errorMsg);
        }
    }

    /**
     * @param string $dtmUrl
     * @param callable $cb
     * @return string
     * @throws \Exception
     */
    function tccGlobalTransaction(string $dtmUrl, callable $cb): array
    {
        $tcc = new Tcc($dtmUrl, genGid($dtmUrl));
        $tbody = [
            'gid' => $tcc->gid,
            'trans_type' => 'tcc',
        ];
        $client = new \GuzzleHttp\Client();
        $message = '操作成功';
        $gid = $tcc->gid;
        try {
            $response = $client->post($tcc->dtm . '/prepare', ['json' => $tbody]);
            checkStatus($response->getStatusCode());
            $cb($tcc);
            $client->post($tcc->dtm . '/submit', ['json' => $tbody]);
        } catch (\Throwable $e) {
            $client->post($tcc->dtm . '/abort', ['json' => $tbody]);
            $message = $e->getMessage();
            $gid = '';
        }
        return [
            'gid'   => $gid,
            'message'   => $message
        ];
    }

    /**
     * @param string $dtmUrl
     * @param string $gid
     * @param string $branchId
     * @return Tcc
     */
    function tccFromReq(string $dtmUrl, string $gid, string $branchId): Tcc
    {
        if (!$dtmUrl || !$gid || !$branchId) {
            throw new \InvalidArgumentException("bad req info for tcc dtm: {$dtmUrl} gid: {$gid} branchId: {$branchId}");
        }
        $tcc = new Tcc($dtmUrl, $gid);
        $tcc->idGen = new IdGenerator($branchId);
        return $tcc;
    }
}
