<?php

/**
 * Class ManageableDataObjectExtension
 */
class ManageableDataObjectExtension extends Extension
{

    /**
     * @var array
     */
    private static $allowed_actions = [
        'add',
        'edit',
        'delete',
        'ManageableDataObjectForm',
    ];

    /**
     * Add object
     *
     * @return SS_HTTPResponse|ViewableData_Customised
     */
    public function add()
    {
        $model = $this->owner->config()->get('managed_object');
        $object = Injector::inst()->get($model);
        if ($object->canCreate(Member::currentUser())) {

            $form = $this->ManageableDataObjectForm();
            if($object->config()->get('add_form_cancel_button')){
                $form->Actions()->push(new CancelFormAction($this->owner->Link(), 'Cancel'));
            }

            return $this->owner->customise([
                'Title' => ($this->owner->config()->get('add_item_title'))
                    ? $this->owner->config()->get('add_item_title')
                    : 'Add new ' . $object->singular_name(),
                'ManageableDataObjectForm' => $form,
            ]);
        }

        return Security::permissionFailure($this->owner, "You don't have permission to add records.");
    }

    /**
     * Edit object
     *
     * @return SS_HTTPResponse|ViewableData_Customised
     */
    public function edit()
    {
        if ($item = $this->getCurrentItem()) {
            if ($item->canEdit(Member::currentUser())) {

                // get Form
                $form = $this->ManageableDataObjectForm($item);

                return $this->owner->customise([
                    'Title' => 'Edit ' . $item->singular_name(),
                    'ManageableDataObjectForm' => $form,
                    'Item' => $item,
                ]);
            }

            return Security::permissionFailure($this->owner, "You don't have permission to edit this record.");
        }

        return $this->owner->httpError(404);
    }

    /**
     * Delete Object
     *
     * @return SS_HTTPResponse
     */
    public function delete()
    {
        if ($item = $this->getCurrentItem()) {
            if ($item->canDelete(Member::currentUser())) {
                if ($item->hasMethod('softDelete')) {
                    $item->softDelete();
                } else {
                    $item->delete();
                }

                return $this->owner->redirect($this->owner->Link());
            }

            return Security::permissionFailure($this->owner, "You don't have permission to delete this record.");
        }

        return $this->owner->httpError(404);
    }

    /**
     * Main GridObject Form. Fields loaded via getFrontEndFields method on each Object
     *
     * @param $object
     *
     * @return ManageableDataObjectForm
     */
    public function ManageableDataObjectForm($object = null)
    {
        $model = $this->owner->config()->get('managed_object');
        $field = ($this->owner->config()->get('query_field'))
            ? $this->owner->config()->get('query_field')
            : 'ID';
        $object = ($object !== null && $object instanceof $model && $object->exists())
            ? $object
            : Injector::inst()->create($model);

        $form = ManageableDataObjectForm::create(
            $this->owner,
            'ManageableDataObjectForm',
            $object
        );

        if ($object->exists()) {
            $form->Fields()->push(HiddenField::create($field, $object->$field));
            $form->loadDataFrom($object);
        }

        return $form;
    }

    /**
     * Save object
     *
     * @param $data
     * @param Form $form
     *
     * @return SS_HTTPResponse
     */
    public function doSaveObject($data, Form $form)
    {

        $model = $this->owner->config()->get('managed_object');

        if (isset($data['ID']) && $data['ID']) {
            $field = ($this->owner->config()->get('query_field'))
                ? $this->owner->config()->get('query_field')
                : 'ID';
            $object = $model::get()->filter($field, $data['ID'])->first();
        } else {
            $object = $model::create();
            if ($object->hasDatabaseField('URLSegment')) {
                $object->URLSegment = Injector::inst()->create(SiteTree::class)->generateURLSegment($data['Title']);
            }
            // write on create to relations are saved on final write (needs ID)
            $object->write();
        }

        $form->saveInto($object);

        $this->owner->extend('updateObjectPreSave', $data, $object, $form);

        $object->write();

        $this->owner->extend('updateObjectPostSave', $data, $object, $form);

        return $this->owner->redirect($object->Link());
    }


    /**
     * @return bool|DataObject
     */
    protected function getCurrentItem()
    {
        if (!$id = $this->owner->request->param('ID')) {
            return false;
        }

        $class = $this->owner->config()->get('managed_object');
        $field = (Injector::inst()->get($class)->config()->get('query_field'))
            ? Injector::inst()->get($class)->config()->get('query_field')
            : 'ID';

        if (!$record = $class::get()->filter($field, $id)->first()) {
            return false;
        }

        return $record;
    }

}