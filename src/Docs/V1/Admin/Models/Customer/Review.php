<?php

namespace NexaMerchant\Apis\Docs\V1\Admin\Models\Customer;

/**
 * @OA\Schema(
 *     title="Review",
 *     description="Review model",
 * )
 */
class Review
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="ID",
     *     format="int64",
     *     example=1
     * )
     *
     * @var int
     */
    private $id;

    /**
     * @OA\Property(
     *     title="Title",
     *     description="Review title",
     *     example="Good Product & Fast Service"
     * )
     *
     * @var string
     */
    private $title;

    /**
     * @OA\Property(
     *     title="Rating",
     *     description="Review rating",
     *     format="int64",
     *     example=4,
     *     enum={"5", "4", "3", "2", "1"}
     * )
     *
     * @var int
     */
    private $rating;

    /**
     * @OA\Property(
     *     title="Comment",
     *     description="Review comment",
     *     example="Lorem Ipsum is simply dummy text of the printing and typesetting industry."
     * )
     *
     * @var string
     */
    private $comment;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="Customer Name",
     *     example="Peter Doe"
     * )
     *
     * @var string
     */
    private $name;

    /**
     * @OA\Property(
     *     title="Status",
     *     description="Review status",
     *     enum={"pending", "approved", "disapproved"}
     * )
     *
     * @var string
     */
    private $status;

    /**
     * @OA\Property(
     *     title="Created at",
     *     description="Created at",
     *     example="2020-01-27 17:50:45",
     *     format="datetime",
     *     type="string"
     * )
     *
     * @var \DateTime
     */
    private $created_at;

    /**
     * @OA\Property(
     *     title="Updated at",
     *     description="Updated at",
     *     example="2020-01-27 17:50:45",
     *     format="datetime",
     *     type="string"
     * )
     *
     * @var \DateTime
     */
    private $updated_at;

    /**
     * @OA\Property(
     *     title="Product",
     *     description="Reviewed Product"
     * )
     *
     * @var \NexaMerchant\Apis\Docs\V1\Shop\Models\Catalog\Product
     */
    private $product;

    /**
     * @OA\Property(
     *     title="Customer",
     *     description="Review's Customer"
     * )
     *
     * @var \NexaMerchant\Apis\Docs\V1\Admin\Models\Customer\Customer
     */
    private $customer;

    /**
     * @OA\Property(
     *     title="Attachments",
     *     description="Review's Attachments"
     * )
     *
     * @var \NexaMerchant\Apis\Docs\V1\Admin\Models\Customer\Attachment
     */
    private $attachments;

    /**
     * @OA\Property(
     *     title="Sort",
     *     description="Sort",
     *     format="int64",
     *     example=0
     * )
     *
     * @var int
     */
    private $sort;
}
