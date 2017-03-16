<?php

namespace Auth\Model;


use Auth\Entity\MemberEntity;
use Auth\Entity\MemberInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Authentication\Result;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Update;
use Zend\Hydrator\HydratorInterface;

class MemberModel
{
    const TABLE_NAME = 'member';

    /**
     * @var AdapterInterface
     */
    protected $db;

    /**
     * @var HydratorInterface
     */
    protected $hydrator;

    /**
     * @var MemberInterface
     */
    protected $memberPrototype;

    /**
     * MemberModel constructor.
     * @param AdapterInterface $db
     * @param HydratorInterface $hydrator
     * @param MemberInterface $memberPrototype
     */
    public function __construct(
        AdapterInterface $db,
        HydratorInterface $hydrator,
        MemberInterface $memberPrototype
    )
    {
        $this->db = $db;
        $this->hydrator = $hydrator;
        $this->memberPrototype = $memberPrototype;
    }

    /**
     * @param int $id
     * @return MemberInterface
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function getMember($id)
    {
        $sql       = new Sql($this->db);
        $select    = $sql->select('member');
        $select->where(['member_id = ?' => $id]);

        $statement = $sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new \RuntimeException(sprintf(
                'Failed retrieving contact with identifier "%s"; unknown database error.',
                $id
            ));
        }

        $resultSet = new HydratingResultSet($this->hydrator, $this->memberPrototype);
        $resultSet->initialize($result);
        $member = $resultSet->current();

        if (! $member) {
            throw new \InvalidArgumentException(sprintf(
                'Contact with identifier "%s" not found.',
                $id
            ));
        }

        return $member;
    }

    /**
     * @param int $linkedinId
     * @return MemberInterface
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function getMemberByLinkedinId($linkedinId)
    {
        $sql       = new Sql($this->db);
        $select    = $sql->select('member');
        $select->where(['linkedin_id = ?' => $linkedinId]);

        $statement = $sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        if (! $result instanceof ResultInterface || ! $result->isQueryResult()) {
            throw new \RuntimeException(sprintf(
                'Failed retrieving contact with identifier "%s"; unknown database error.',
                $linkedinId
            ));
        }

        $resultSet = new HydratingResultSet($this->hydrator, $this->memberPrototype);
        $resultSet->initialize($result);
        $member = $resultSet->current();

        if (! $member) {
            throw new \InvalidArgumentException(sprintf(
                'Contact with identifier "%s" not found.',
                $linkedinId
            ));
        }

        return $member;
    }

    /**
     * Saves an instance of a member interface
     *
     * @param MemberInterface $member
     * @return MemberInterface
     */
    public function saveMember(MemberInterface $member)
    {
        if (0 < (int) $member->getMemberId()) {
            return $this->updateMember($member);
        }
        return $this->insertMember($member);
    }

    /**
     * Stores a new Member entity
     *
     * @param MemberInterface $member
     * @return MemberEntity
     */
    public function insertMember(MemberInterface $member)
    {
        $date = new \DateTime();
        $insert = new Insert('member');
        $data = $this->hydrator->extract($member);
        $data['modified'] = $date->format('Y-m-d H:i:s');
        if (0 === (int) $member->getMemberId()) {
            $data['created'] = $date->format('Y-m-d H:i:s');
            unset ($data['member_id']);
        }
        $insert->values($data);
        $sql = new Sql($this->db);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        $result = $stmt->execute();

        if (!$result instanceof ResultInterface) {
            throw new \RuntimeException('Database error occurred during storage of new member');
        }

        $memberId = (int) $result->getGeneratedValue();
        return new MemberEntity($memberId, $member->getLinkedinId(), $member->getAccessToken());
    }

    /**
     * Updates a member entity
     *
     * @param MemberInterface $member
     * @return MemberInterface
     */
    private function updateMember(MemberInterface $member)
    {
        $date = new \DateTime();
        $update = new Update('member');
        $data = $this->hydrator->extract($member);
        $data['modified'] = $date->format('Y-m-d H:i:s');
        unset ($data['member_id']);

        $update->set($data)
        ->where(['member_id = ?' => (int) $member->getMemberId()]);

        $sql = new Sql($this->db);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $result = $stmt->execute();

        if (!$result instanceof ResultInterface) {
            throw new \RuntimeException('Database error occurred during storage of new member');
        }

        return $member;
    }
}