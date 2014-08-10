<?php
/**
 * The MIT License (MIT)
 *
 * Webzash - Easy to use web based double entry accounting software
 *
 * Copyright (c) 2014 Prashant Shah <pshah.mumbai@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

App::uses('WebzashAppController', 'Webzash.Controller');

/**
 * Webzash Plugin Entries Controller
 *
 * @package Webzash
 * @subpackage Webzash.Controllers
 */
class EntriesController extends WebzashAppController {

/**
 * index method
 *
 * @return void
 */
	public function index() {

		$this->set('title_for_layout', __d('webzash', 'List Of Entries'));

		$this->loadModel('Entrytype');

		$conditions = array();

		/* Filter by entry type */
		if (isset($this->passedArgs['show'])) {
			$entrytype = $this->Entrytype->find('first', array('conditions' => array('Entrytype.label' => $this->passedArgs['show'])));
			if (!$entrytype) {
				$this->Session->setFlash(__d('webzash', 'Entry type not found. Showing all entries.'), 'error');
				return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
			}

			$conditions['Entry.entrytype_id'] = $entrytype['Entrytype']['id'];
		}

		/* Filter by tag */
		if (isset($this->passedArgs['tag'])) {
			$conditions['Entry.tag_id'] = $this->passedArgs['tag'];
		}

		/* Setup pagination */
		$this->Paginator->settings = array(
			'Entry' => array(
				'limit' => 10,
				'conditions' => $conditions,
				'order' => array('Entry.date' => 'desc'),
			)
		);

		if ($this->request->is('post')) {
			if (empty($this->request->data['Entry']['show'])) {
				return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
			} else {
				return $this->redirect(array('controller' => 'entries', 'action' => 'index', 'show' => $this->request->data['Entry']['show']));
			}
		}

		if (empty($this->passedArgs['show'])) {
			$this->request->data['Entry']['show'] = '0';
		} else {
			$this->request->data['Entry']['show'] = $this->passedArgs['show'];
		}

		$this->set('entries', $this->Paginator->paginate('Entry'));
		return;
	}

/**
 * show method
 *
 * @param string $entrytypeLabel
 * @return void
 */
	public function show($entrytypeLabel = null) {

		$this->set('title_for_layout', __d('webzash', 'List Of Entries'));

		$this->loadModel('Entrytype');

		/* Check for valid entry type */
		if (empty($entrytypeLabel)) {
			$this->Session->setFlash(__d('webzash', 'Entry type not specified. Showing all entries.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}
		$entrytype = $this->Entrytype->find('first', array('conditions' => array('Entrytype.label' => $entrytypeLabel)));
		if (!$entrytype) {
			$this->Session->setFlash(__d('webzash', 'Entry type not found. Showing all entries.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}

		$this->set('actionlinks', array(
			array('controller' => 'entries', 'action' => 'add', 'data' => $entrytype['Entrytype']['label'], 'title' => __d('webzash', 'Add ') . $entrytype['Entrytype']['name']),
		));

		/* Setup pagination */
		$this->Paginator->settings = array(
			'Entry' => array(
				'limit' => 10,
				'conditions' => array('Entry.entrytype_id' => $entrytype['Entrytype']['id']),
				'order' => array('Entry.date' => 'desc'),
			)
		);

		$this->set('entries', $this->Paginator->paginate('Entry'));

		$this->set('entrytype', $entrytype);

		return;
	}

/**
 * add method
 *
 * @param string $entrytypeLabel
 * @return void
 */
	public function add($entrytypeLabel = null) {

		$this->set('title_for_layout', __d('webzash', 'Add Entry'));

		/* TODO : Test code */
		$this->Session->write('startDate', '2014-04-01 02:00:00');
		$this->Session->write('endDate', '2015-03-31 00:59:00');

		$this->loadModel('Entrytype');
		$this->loadModel('Entryitem');
		$this->loadModel('Ledger');

		/* Check for valid entry type */
		if (!$entrytypeLabel) {
			$this->Session->setFlash(__d('webzash', 'Entry type not specified.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}
		$entrytype = $this->Entrytype->find('first', array('conditions' => array('Entrytype.label' => $entrytypeLabel)));
		if (!$entrytype) {
			$this->Session->setFlash(__d('webzash', 'Entry type not found.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}
		$this->set('entrytype', $entrytype);

		/* Initial data */
		if ($this->request->is('post')) {
			$curEntryitems = array();
			foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
				$curEntryitems[$row] = array(
					'dc' => $entryitem['dc'],
					'ledger_id' => $entryitem['ledger_id'],
					'dr_amount' => isset($entryitem['dr_amount']) ? $entryitem['dr_amount'] : '',
					'cr_amount' => isset($entryitem['cr_amount']) ? $entryitem['cr_amount'] : '',
				);
			}
			$this->set('curEntryitems', $curEntryitems);
		} else {
			$curEntryitems = array();
			if ($entrytype['Entrytype']['restriction_bankcash'] == 3) {
				/* Special case if atleast one Bank or Cash on credit side (3) then 1st item is Cr */
				$curEntryitems[0] = array('dc' => 'C');
				$curEntryitems[1] = array('dc' => 'D');
			} else {
				/* Otherwise 1st item is Dr */
				$curEntryitems[0] = array('dc' => 'D');
				$curEntryitems[1] = array('dc' => 'C');
			}
			$curEntryitems[2] = array('dc' => 'D');
			$curEntryitems[3] = array('dc' => 'D');
			$curEntryitems[4] = array('dc' => 'D');
			$this->set('curEntryitems', $curEntryitems);
		}

		/* On POST */
		if ($this->request->is('post')) {
			if (!empty($this->request->data)) {

				/***************************************************************************/
				/*********************************** ENTRY *********************************/
				/***************************************************************************/

				$entrydata = null;

				/* Entry id */
				unset($this->request->data['Entry']['id']);

				/***** Check and update entry number ******/
				if ($entrytype['Entrytype']['numbering'] == 1) {
					/* Auto */
					if (empty($this->request->data['Entry']['number'])) {
						$entrydata['Entry']['number'] = $this->Entry->nextNumber($entrytype['Entrytype']['id']);
					} else {
						$entrydata['Entry']['number'] = $this->request->data['Entry']['number'];
					}
				} else if ($entrytype['Entrytype']['numbering'] == 2) {
					/* Manual + Required */
					if (empty($this->request->data['Entry']['number'])) {
						$this->Session->setFlash(__d('webzash', 'Entry number cannot be empty'), 'error');
						return;
					} else {
						$entrydata['Entry']['number'] = $this->request->data['Entry']['number'];
					}
				} else {
					/* Manual + Optional */
					$entrydata['Entry']['number'] = $this->request->data['Entry']['number'];
				}

				/****** Check entry type *****/
				$entrydata['Entry']['entrytype_id'] = $entrytype['Entrytype']['id'];

				/****** Check tag ******/
				if (empty($this->request->data['Entry']['tag_id'])) {
					$entrydata['Entry']['tag_id'] = null;
				} else {
					$entrydata['Entry']['tag_id'] = $this->request->data['Entry']['tag_id'];
				}

				/***** Narration *****/
				$entrydata['Entry']['narration'] = $this->request->data['Entry']['narration'];

				/***** Date *****/
				$entrydata['Entry']['date'] = dateToSql($this->request->data['Entry']['date'], '00:00:00');

				/***************************************************************************/
				/***************************** ENTRY ITEMS *********************************/
				/***************************************************************************/

				/* Check ledger restriction */
				$dc_valid = false;
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}
					$ledger = $this->Ledger->findById($entryitem['ledger_id']);
					if (!$ledger) {
						$this->Session->setFlash(__d('webzash', 'Invalid ledger'), 'error');
						return;
					}

					if ($entrytype['Entrytype']['restriction_bankcash'] == 4) {
						if ($ledger['Ledger']['type'] != 1) {
							$this->Session->setFlash(__d('webzash', 'Only bank or cash ledgers are allowed'), 'error');
							return;
						}
					}
					if ($entrytype['Entrytype']['restriction_bankcash'] == 5) {
						if ($ledger['Ledger']['type'] == 1) {
							$this->Session->setFlash(__d('webzash', 'Bank or cash ledgers are not allowed'), 'error');
							return;
						}
					}

					if ($entryitem['dc'] == 'D') {
						if ($entrytype['Entrytype']['restriction_bankcash'] == 2) {
							if ($ledger['Ledger']['type'] == 1) {
								$dc_valid = true;
							}
						}
					} else if ($entryitem['dc'] == 'C') {
						if ($entrytype['Entrytype']['restriction_bankcash'] == 3) {
							if ($ledger['Ledger']['type'] == 1) {
								$dc_valid = true;
							}
						}
					}
				}
				if ($entrytype['Entrytype']['restriction_bankcash'] == 2) {
					if (!$dc_valid) {
						$this->Session->setFlash(__d('webzash', 'Atleast one bank or cash ledger has to be on debit side'), 'error');
						return;
					}
				}
				if ($entrytype['Entrytype']['restriction_bankcash'] == 3) {
					if (!$dc_valid) {
						$this->Session->setFlash(__d('webzash', 'Atleast one bank or cash ledger has to be on credit side'), 'error');
						return;
					}
				}

				$dr_total = 0;
				$cr_total = 0;

				/* Check equality of debit and credit total */
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}

					if ($entryitem['dc'] == 'D') {
						if ($entryitem['dr_amount'] <= 0) {
							$this->Session->setFlash(__d('webzash', 'Invalid amount'), 'error');
							return;
						}
						$dr_total = calculate($dr_total, $entryitem['dr_amount'], '+');
					} else if ($entryitem['dc'] == 'C') {
						if ($entryitem['cr_amount'] <= 0) {
							$this->Session->setFlash(__d('webzash', 'Invalid amount'), 'error');
							return;
						}
						$cr_total = calculate($cr_total, $entryitem['cr_amount'], '+');
					} else {
						$this->Session->setFlash(__d('webzash', 'Invalid Dr/Cr'), 'error');
						return;
					}
				}
				if (calculate($dr_total, $cr_total, '!=')) {
					$this->Session->setFlash(__d('webzash', 'Debit and Credit total do not match'), 'error');
					return;
				}

				$entrydata['Entry']['dr_total'] = $dr_total;
				$entrydata['Entry']['cr_total'] = $cr_total;

				/* Add item to entryitemdata array if everything is ok */
				$entryitemdata = array();
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}
					if ($entryitem['dc'] == 'D') {
						$entryitemdata[] = array(
							'Entryitem' => array(
								'dc' => $entryitem['dc'],
								'ledger_id' => $entryitem['ledger_id'],
								'amount' => $entryitem['dr_amount'],
							)
						);
					} else {
						$entryitemdata[] = array(
							'Entryitem' => array(
								'dc' => $entryitem['dc'],
								'ledger_id' => $entryitem['ledger_id'],
								'amount' => $entryitem['cr_amount'],
							)
						);
					}
				}

				/* Save entry */
				$ds = $this->Entry->getDataSource();
				$ds->begin();

				$this->Entry->create();
				if ($this->Entry->save($entrydata)) {
					/* Save entry items */
					foreach ($entryitemdata as $row => $itemdata) {
						$itemdata['Entryitem']['entry_id'] = $this->Entry->id;
						$this->Entryitem->create();
						if (!$this->Entryitem->save($itemdata)) {
							$ds->rollback();
							$this->Session->setFlash(__d('webzash', 'Failed to save entry ledgers'), 'error');
							return;
						}
					}
					$ds->commit();
					$this->Session->setFlash(__d('webzash', 'The entry has been created.'), 'success');
					return $this->redirect(array('controller' => 'entries', 'action' => 'show', $entrytype['Entrytype']['label']));
				} else {
					$ds->rollback();
					$this->Session->setFlash(__d('webzash', 'The entry could not be saved. Please, try again.'), 'error');
					return;
				}
			} else {
				$this->Session->setFlash(__d('webzash', 'No data. Please, try again.'), 'error');
				return;
			}
		}
	}


/**
 * edit method
 *
 * @param string $entrytypeLabel
 * @param string $id
 * @return void
 */
	public function edit($entrytypeLabel = null, $id = null) {

		$this->set('title_for_layout', __d('webzash', 'Edit Entry'));

		$this->loadModel('Entrytype');
		$this->loadModel('Entryitem');
		$this->loadModel('Ledger');

		/* Check for valid entry type */
		if (!$entrytypeLabel) {
			$this->Session->setFlash(__d('webzash', 'Entry type not specified.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}
		$entrytype = $this->Entrytype->find('first', array('conditions' => array('Entrytype.label' => $entrytypeLabel)));
		if (!$entrytype) {
			$this->Session->setFlash(__d('webzash', 'Entry type not found.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}
		$this->set('entrytype', $entrytype);

		/* Check for valid entry id */
		if (empty($id)) {
			$this->Session->setFlash(__d('webzash', 'Entry not specified.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}
		$entry = $this->Entry->findById($id);
		if (!$entry) {
			$this->Session->setFlash(__d('webzash', 'Entry not found.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'all'));
		}

		/* Initial data */
		if ($this->request->is('post') || $this->request->is('put')) {
			$curEntryitems = array();
			foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
				$curEntryitems[$row] = array(
					'dc' => $entryitem['dc'],
					'ledger_id' => $entryitem['ledger_id'],
					'dr_amount' => isset($entryitem['dr_amount']) ? $entryitem['dr_amount'] : '',
					'cr_amount' => isset($entryitem['cr_amount']) ? $entryitem['cr_amount'] : '',
				);
			}
			$this->set('curEntryitems', $curEntryitems);
		} else {
			$curEntryitems = array();
			$curEntryitemsData = $this->Entryitem->find('all', array(
				'conditions' => array('Entryitem.entry_id' => $id),
			));
			foreach ($curEntryitemsData as $row => $data) {
				if ($data['Entryitem']['dc'] == 'D') {
					$curEntryitems[$row] = array(
						'dc' => $data['Entryitem']['dc'],
						'ledger_id' => $data['Entryitem']['ledger_id'],
						'dr_amount' => $data['Entryitem']['amount'],
						'cr_amount' => '',
					);
				} else {
					$curEntryitems[$row] = array(
						'dc' => $data['Entryitem']['dc'],
						'ledger_id' => $data['Entryitem']['ledger_id'],
						'dr_amount' => '',
						'cr_amount' => $data['Entryitem']['amount'],
					);
				}
			}
			$curEntryitems[] = array('dc' => 'D');
			$curEntryitems[] = array('dc' => 'D');
			$curEntryitems[] = array('dc' => 'D');
			$this->set('curEntryitems', $curEntryitems);
		}

		/* On POST */
		if ($this->request->is('post') || $this->request->is('put')) {
			if (!empty($this->request->data)) {

				/***************************************************************************/
				/*********************************** ENTRY *********************************/
				/***************************************************************************/

				$entrydata = null;

				/* Entry id */
				unset($this->request->data['Entry']['id']);
				$this->Entry->id = $id;
				$entrydata['Entry']['id'] = $id;

				/***** Entry number ******/
				$entrydata['Entry']['number'] = $this->request->data['Entry']['number'];

				/****** Entrytype remains the same *****/
				$entrydata['Entry']['entrytype_id'] = $entrytype['Entrytype']['id'];

				/****** Check tag ******/
				if (empty($this->request->data['Entry']['tag_id'])) {
					$entrydata['Entry']['tag_id'] = null;
				} else {
					$entrydata['Entry']['tag_id'] = $this->request->data['Entry']['tag_id'];
				}

				/***** Narration *****/
				$entrydata['Entry']['narration'] = $this->request->data['Entry']['narration'];

				/***** Date *****/
				$entrydata['Entry']['date'] = dateToSql($this->request->data['Entry']['date'], '00:00:00');

				/***************************************************************************/
				/***************************** ENTRY ITEMS *********************************/
				/***************************************************************************/

				/* Check ledger restriction */
				$dc_valid = false;
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}
					$ledger = $this->Ledger->findById($entryitem['ledger_id']);
					if (!$ledger) {
						$this->Session->setFlash(__d('webzash', 'Invalid ledger'), 'error');
						return;
					}

					if ($entrytype['Entrytype']['restriction_bankcash'] == 4) {
						if ($ledger['Ledger']['type'] != 1) {
							$this->Session->setFlash(__d('webzash', 'Only bank or cash ledgers are allowed'), 'error');
							return;
						}
					}
					if ($entrytype['Entrytype']['restriction_bankcash'] == 5) {
						if ($ledger['Ledger']['type'] == 1) {
							$this->Session->setFlash(__d('webzash', 'Bank or cash ledgers are not allowed'), 'error');
							return;
						}
					}

					if ($entryitem['dc'] == 'D') {
						if ($entrytype['Entrytype']['restriction_bankcash'] == 2) {
							if ($ledger['Ledger']['type'] == 1) {
								$dc_valid = true;
							}
						}
					} else if ($entryitem['dc'] == 'C') {
						if ($entrytype['Entrytype']['restriction_bankcash'] == 3) {
							if ($ledger['Ledger']['type'] == 1) {
								$dc_valid = true;
							}
						}
					}
				}
				if ($entrytype['Entrytype']['restriction_bankcash'] == 2) {
					if (!$dc_valid) {
						$this->Session->setFlash(__d('webzash', 'Atleast one bank or cash ledger has to be on debit side'), 'error');
						return;
					}
				}
				if ($entrytype['Entrytype']['restriction_bankcash'] == 3) {
					if (!$dc_valid) {
						$this->Session->setFlash(__d('webzash', 'Atleast one bank or cash ledger has to be on credit side'), 'error');
						return;
					}
				}

				$dr_total = 0;
				$cr_total = 0;

				/* Check equality of debit and credit total */
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}

					if ($entryitem['dc'] == 'D') {
						if ($entryitem['dr_amount'] <= 0) {
							$this->Session->setFlash(__d('webzash', 'Invalid amount'), 'error');
							return;
						}
						$dr_total = calculate($dr_total, $entryitem['dr_amount'], '+');
					} else if ($entryitem['dc'] == 'C') {
						if ($entryitem['cr_amount'] <= 0) {
							$this->Session->setFlash(__d('webzash', 'Invalid amount'), 'error');
							return;
						}
						$cr_total = calculate($cr_total, $entryitem['cr_amount'], '+');
					} else {
						$this->Session->setFlash(__d('webzash', 'Invalid Dr/Cr'), 'error');
						return;
					}
				}
				if (calculate($dr_total, $cr_total, '!=')) {
					$this->Session->setFlash(__d('webzash', 'Debit and Credit total do not match'), 'error');
					return;
				}

				$entrydata['Entry']['dr_total'] = $dr_total;
				$entrydata['Entry']['cr_total'] = $cr_total;

				/* Add item to entryitemdata array if everything is ok */
				$entryitemdata = array();
				foreach ($this->request->data['Entryitem'] as $row => $entryitem) {
					if ($entryitem['ledger_id'] <= 0) {
						continue;
					}
					if ($entryitem['dc'] == 'D') {
						$entryitemdata[] = array(
							'Entryitem' => array(
								'dc' => $entryitem['dc'],
								'ledger_id' => $entryitem['ledger_id'],
								'amount' => $entryitem['dr_amount'],
							)
						);
					} else {
						$entryitemdata[] = array(
							'Entryitem' => array(
								'dc' => $entryitem['dc'],
								'ledger_id' => $entryitem['ledger_id'],
								'amount' => $entryitem['cr_amount'],
							)
						);
					}
				}

				/* Save entry */
				$ds = $this->Entry->getDataSource();
				$ds->begin();

				if ($this->Entry->save($entrydata)) {

					/* Delete all original entryitems */
					if (!$this->Entryitem->deleteAll(array('Entryitem.entry_id' => $id))) {
						$ds->rollback();
						$this->Session->setFlash(__d('webzash', 'Previous entry items could not be deleted. Please, try again.'), 'error');
						return;
					}

					/* Save new entry items */
					foreach ($entryitemdata as $id => $itemdata) {
						$itemdata['Entryitem']['entry_id'] = $this->Entry->id;
						$this->Entryitem->create();
						if (!$this->Entryitem->save($itemdata)) {
							$ds->rollback();
							$this->Session->setFlash(__d('webzash', 'Failed to save entry ledgers'), 'error');
							return;
						}
					}
					$ds->commit();
					$this->Session->setFlash(__d('webzash', 'The entry has been updated.'), 'success');
					return $this->redirect(array('controller' => 'entries', 'action' => 'show', $entrytype['Entrytype']['label']));
				} else {
					$ds->rollback();
					$this->Session->setFlash(__d('webzash', 'The entry could not be updated. Please, try again.'), 'error');
					return;
				}
			} else {
				$this->Session->setFlash(__d('webzash', 'No data. Please, try again.'), 'error');
				return;
			}
		} else {
			$entry['Entry']['date'] = dateFromSql($entry['Entry']['date']);
			$this->request->data = $entry;
			return;
		}
	}

/**
 * delete method
 *
 * @throws MethodNotAllowedException
 * @param string $entrytypeLabel
 * @param string $id
 * @return void
 */
	public function delete($entrytypeLabel = null, $id = null) {
		$this->loadModel('Entryitem');
		$this->loadModel('Entrytype');
		$this->loadModel('Ledger');

		/* Check for valid entry type */
		if (empty($entrytypeLabel)) {
			$this->Session->setFlash(__d('webzash', 'Entry type not specified. Showing all entries.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}
		$entrytype = $this->Entrytype->find('first', array('conditions' => array('Entrytype.label' => $entrytypeLabel)));
		if (!$entrytype) {
			$this->Session->setFlash(__d('webzash', 'Entry type not found. Showing all entries.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}

		/* GET access not allowed */
		if ($this->request->is('get')) {
			throw new MethodNotAllowedException();
		}

		/* Check if valid id */
		if (empty($id)) {
			$this->Session->setFlash(__d('webzash', 'Entry not specified.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}

		/* Check if entry exists */
		if (!$this->Entry->exists($id)) {
			$this->Session->setFlash(__d('webzash', 'Entry not found.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'index'));
		}

		$ds = $this->Entry->getDataSource();
		$ds->begin();

		/* Delete entry items */
		if (!$this->Entryitem->deleteAll(array('Entryitem.entry_id' => $id))) {
			$ds->rollback();
			$this->Session->setFlash(__d('webzash', 'The entry items could not be deleted. Please, try again.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'show', $entrytype['Entrytype']['label']));
		}

		/* Delete entry */
		if (!$this->Entry->delete($id)) {
			$ds->rollback();
			$this->Session->setFlash(__d('webzash', 'The entry could not be deleted. Please, try again.'), 'error');
			return $this->redirect(array('controller' => 'entries', 'action' => 'show', $entrytype['Entrytype']['label']));
		}

		$ds->commit();

		$this->Session->setFlash(__d('webzash', 'The entry has been deleted.'), 'success');
		return $this->redirect(array('controller' => 'entries', 'action' => 'show', $entrytype['Entrytype']['label']));
	}

/**
 * Add a row in the entry via ajax
 *
 * @param string $addType
 * @return void
 */
	function addrow($addType = 'all')
	{
		$this->loadModel('Ledger');
		$this->layout = null;

		$ledgers[0] = '(Please select..)';
		$rawledgers = array();

		if ($addType == 'bankcash') {
			$rawledgers = $this->Ledger->find('all', array('conditions' => array('Ledger.type' => '1'), 'order' => 'Ledger.name'));
		} else if ($addType == 'nonbankcash') {
			$rawledgers = $this->Ledger->find('all', array('conditions' => array('Ledger.type' => '0'), 'order' => 'Ledger.name'));
		} else {
			$rawledgers = $this->Ledger->find('all', array('order' => 'Ledger.name'));
		}

		foreach ($rawledgers as $rawledger) {
			$ledgers[$rawledger['Ledger']['id']] = $rawledger['Ledger']['name'];
		}

		$this->set('ledgers', $ledgers);
	}

}
